<?php
namespace FileMover;

use FileMover\Library\Logger;
use FileMover\Library\Mover;
use FileMover\Library\Poster;
use FileMover\Library\Unzipper;
use FileMover\Library\Zipper;

// Explicitly set the current TimeZone to be used.
// If it is already set in the php.ini, GREAT, keep it!
// If not, set it to UTC.
$tz = ini_get('date.timezone') ?: 'UTC';
ini_set('date.timezone', $tz);

define('SEVERELY_OLD', 3600 * 2); // 2 Hours old.
define('ONE_HOUR', 3600);

// Autoloading:
require_once __DIR__ . '/vendor/autoload.php';

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

            if (basename($file) === '.po2go') {
                continue;
            }


            $mimeType = mime_content_type($file);
            Logger::logMessage(basename($file) . ' is a ' . $mimeType, 'MIMETypes.log');

            $result = false;
            // if we have a URL, POST it.
            // if we have a local directory path, move it!
            $url = $ruleSet->url ?: false;
            if ($url) {

                $result = array();

                switch ($mimeType) {
                    case 'application/x-rar-compressed': // .rar
                    case 'application/x-bzip2':          // .bz2
                    case 'application/x-gzip':           // .tgz; .gz
                        // For now, we don't handle these archives.
                        $result['post'] = false;
                        break;
                    case 'application/zip':              // .zip (includes .war & .jar)
                        // Unzip, create multipart, and POST it.
                        $extraction = Unzipper::extractZipArchive($file);
                        if ($extraction) {
                            $tmpDirName = basename($file,'.zip') . '_TMP';
                            $result['post'] = Poster::curlMultiPartData($url, $tmpDirName);
                        }
                        $result['post'] = false;
                        break;
                    default:                             // Not an Archive.
                        // POST the file:
                        $result['post'] = Poster::curlPostRawData($url, file_get_contents($file));
                        break;
                }
            }

            $path = $ruleSet->local_path ?: false;

            if ($path) {
                if (!is_array($result)) {
                    $result = array();
                }
                $result['transfer'] = Mover::transferFileLocally($path, $file);
            }


            // If the cURL POST responded with an error $result is false.
            if ($result === false || (is_array($result) && ($result['post'] === false || $result['transfer'] === false))) {
                // Log failure, check age, Log files, then go to the next file.
                $logLevel = 'ERROR';
                // If the file is considered "Severely Old" log to a critical file level, so we will be notified by the log monitor cron.
                $old = SEVERELY_OLD;
                if (!empty($config)) {
                    $old = $config->old ? ($config->old * ONE_HOUR) : SEVERELY_OLD;
                }
                if (fileAge($file) > $old) {
                    $logLevel = 'CRITICAL';
                }

                $message = 'FILE: ' . $file . ' -- delivery failure --' . PHP_EOL;
                if (is_array($result) && $result['post'] === false) {
                    $message .= 'Failed to POST to ' . $url . '. File will remain in place.' . PHP_EOL;
                }
                if (is_array($result) && $result['transfer'] === false) {
                    $message .= 'Failed to MOVE to ' . $path . '. File will remain in place.' . PHP_EOL;
                }
                if (!is_array($result)) {
                    $message .= 'No delivery route set. File will remain in place.' . PHP_EOL;
                }
                $message .= '--------------';
                $fileName = str_replace('/', '_', $directory) . '.log';
                Logger::logMessage($message, $fileName, $logLevel);
                continue;
            }

            // Log results.
            $message = PHP_EOL . "\t" . 'RESULT for FILE (' . $file . '): ' . PHP_EOL . print_r($result,
                    true) . PHP_EOL;
            $fileName = str_replace('/', '_', $directory);
            Logger::logMessage($message, $fileName . '.log', 'INFO');

            // TODO: Read the response and determine if the file should be archived or not.
            // TODO: There may be times where we had a successful POST, but we don't have the file and will need to rePOST.
            // TODO: I don't know what those circumstances might be, so we're not currently handling them.

            // Zip it up and delete it, if it did not get moved. (for POST only files)
            if (file_exists($file)) {
                Zipper::archiveFileAsZip($file, $fileName . '.zip');
            }

        }
    }
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
