<?php
// Explicitly set the current TimeZone to be used.
// If it is already set in the php.ini, GREAT, keep it!
// If not, set it to UTC.
$tz = ini_get('date.timezone')?:'UTC';
ini_set('date.timezone', $tz);

define('SEVERELY_OLD', 60*60*24*2); // 2 Days old.
define('ONE_DAY', 60*60*24);

/**
 * Directories Will be a JSON array of Directory Paths.
 *
 * @var array $directories
 */
$directories = json_decode(file_get_contents(__DIR__ . '/.directories'))->directories;

// Step through each directory
foreach ($directories as $directory) {
    /** var string $directory */
    if (!file_exists($directory . '/.po2go')) {
        // TODO: Add Logging about skipping the directory.
        continue;
    }

    /**
     * The po2goRules will be an array of stdObjects.
     * Each Object should contain a glob pattern and a uri to which the files matching the pattern will be POSTed.
     *
     * @var array $po2goRules
     */
    $jsonObject = json_decode(file_get_contents($directory . '/.po2go'));
    $po2goRules = $jsonObject->rules;
    $config = $jsonObject->configuration;

    // Step through each rule set.
    foreach ($po2goRules as $ruleSet) {

        // Glob will locate files that match the pattern supplied
        foreach (glob($directory . '/' . $ruleSet->pattern) as $file) {

            // POST the file:
            $result = curlPostRawData($ruleSet->url, file_get_contents($file));


            // If the cURL POST responded with an error $result is false.
            if (!$result) {
                // Log failure, check age, Log files, then go to the next file.
                $logLevel = 'ERROR';
                // If the file is considered "Severely Old" log to a critical file level, so we will be notified by the log monitor cron.
                $old = SEVERELY_OLD;
                if (!empty($config)) {
                    $old = $config->old ? ($config->old * ONE_DAY) : SEVERELY_OLD;
                }
                if (fileAge($file) > $old) {
                    $logLevel = 'CRITICAL';
                }

                $message = 'FILE: ' . $file . ' failed to POST to ' . $ruleSet->url . '. File will remain in place.';
                $fileName = str_replace('/', '_', $directory) . '.log';
                logMessage($message, $fileName, $logLevel);
                continue;
            }

            // Log results.
            $message =  PHP_EOL . "\t" . 'RESULT for FILE (' . $file . '): ' . PHP_EOL . print_r($result,true) . PHP_EOL;
            $fileName = str_replace('/', '_', $directory) . '.log';
            logMessage($message, $fileName, 'INFO');

            // TODO: Read the response and determine if the file should be archived or not.
            // TODO: There may be times where we had a successful POST, but we don't have the file and will need to rePOST.
            // TODO: I don't know what those circumstances might be, so we're not currently handling them.

            // Zip it up and delete it.
            archiveFile($file, $fileName . '.zip');

        }
    }
}


/**
 * Send a POST request using cURL
 *
 * @param string $url  Target URL
 * @param string $post Raw Text Data to POST
 *
 * @return string
 */
function curlPostRawData($url, $post = null)
{
    $defaults = array(
        CURLOPT_POST           => 1,
        CURLOPT_HEADER         => 0,
        CURLOPT_URL            => $url,
        CURLOPT_FRESH_CONNECT  => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FORBID_REUSE   => 1,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_POSTFIELDS     => $post
    );

    $ch = curl_init();
    curl_setopt_array($ch, $defaults);
    if (!$result = curl_exec($ch)) {
        // Log Errors
        $message  = PHP_EOL . "\t" . 'cURL Error: ' . curl_error($ch);
        $message .= PHP_EOL . "\t" . 'POST URL  : ' . $url;
        logMessage($message, 'cURLError.log', 'ERROR');
    }
    curl_close($ch);

    return $result;
}

/**
 * Creates 2 log files.
 * One Unified that would contain all messages of any level for easier debugging
 * On "explicit" that has logs grouped by severity based upon level.
 *
 * @param string $message
 * @param string $fileName
 * @param string $level
 */
function logMessage($message, $fileName='FileMover.log', $level='INFO')
{
    $path = __DIR__ . '/var/logs/';

    $definedLevels = [
        'EMERGENCY' => 'SEVERE',    // system is unusable
        'ALERT' => 'SEVERE',        // action must be taken immediately
        'CRITICAL' => 'SEVERE',     // critical conditions
        'ERROR' => 'ERROR',        // error conditions
        'WARNING' => 'MINOR',      // warning conditions
        'NOTICE' => 'MINOR',       // normal, but significant, condition
        'INFO' => 'INFO',         // informational message
        'DEBUG' => 'INFO'        // debug-level message
    ];

    if (!in_array($level, $definedLevels)) {
        $level = 'INFO';
    }

    $now = new DateTime();

    $message = $now->format('Y-m-d H:i:s O') . ' [' . $level . ']: ' . $message . PHP_EOL;

    $explicitFilePath = $path . $definedLevels[$level] . '-' .  $fileName;
    $unifiedFilePath = $path . $fileName;

    // Write to the explicit log level file.
    $handleExplicit = fopen($explicitFilePath, 'ab');
    fwrite($handleExplicit, $message);
    fclose($handleExplicit);

    // Write to ethe unified log level file.
    $handleUnified = fopen($unifiedFilePath, 'ab');
    fwrite($handleUnified, $message);
    fclose($handleUnified);

}

/**
 * This will Zip the incoming file, delete the original and optionally move the archive elsewhere.
 * It is possible to archive multiple file by calling this mulitole times, always using the same
 * $archiveFileName
 *
 * @param string        $originalFileName
 * @param null|string   $archiveFileName
 */
function archiveFile($originalFileName, $archiveFileName = null)
{
   $zip = new ZipArchive();
   if (null === $archiveFileName) {
       $archiveFileName = $originalFileName .'.zip';
   }

   if ($zip->open($archiveFileName, ZipArchive::CREATE) !== true) {
       $message .= 'Unable to create archive "'.$archiveFileName.'" for file "'.$originalFileName.'"';
       $message .= "\t" . 'This file will be re-POSTED if not manually deleted or moved.';
       logMessage($message,'Archive.log','CRITICAL');
       return;
   }

   $zip->addFile($originalFileName);
   $zip->close();

   $deleted = unlink($originalFileName);

   if ($deleted) {
       $message = 'Original File: ' . $originalFileName . ' deleted.';
       logMessage($message, 'Archive.log', 'INFO');
       return;
   }
    $message  = 'Original File: ' . $originalFileName . ' failed to be deleted.';
    $message .= "\t" . 'This file will be re-POSTED if not manually deleted or moved.';
    logMessage($message, 'Archive.log', 'CRITICAL');

}

/**
 * Returns the age of the file.
 *
 * @param string $filename Path to file including filename.
 *
 * @return int   Age of file in seconds.
 */
function fileAge($filename)
{
    $rightNow = time();
    $fileTime = filemtime($filename);

    return $rightNow - $fileTime;
}
