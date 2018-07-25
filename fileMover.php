<?php

namespace FileMover;

use FileMover\Library\Cleaner;
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
$cmd = new \Commando\Command();
$cmd->option('directory')->aka('d')->describedAs('Specific directory to process.');
$cmd->option('url')->aka('u')->describedAs('POST URL.');
$cmd->option('pattern')->aka('p')->describedAs('Glob Pattern for files in directory');
$cmd->option('timeout')->aka('t')->describedAs('curl time out option, in whole seconds. (default: 120)')->defaultsTo('120');
if (!empty($cmd['d'])) {
    $dir = $cmd['d'];
    $directories = [ $dir ];
} else {
    $directories = json_decode(file_get_contents(__DIR__ . '/.directories'))->directories;
}

$curlOpts = ['timeout'=>$cmd['timeout']];

// Step through each directory
foreach ($directories as $directory) {
    /** var string $directory */
    if (!empty($cmd['u'])
        && !empty($cmd['p'])) {
        $po2goRules = array (
            'pattern' => $cmd['p'],
            'url'     => $cmd['u']
        );
        $config_string = null;
    } elseif ( ! file_exists($directory . '/.po2go')) {
        // TODO: Add Logging about skipping the directory.
        continue;
    } else {
        $config_string = file_get_contents($directory . '/.po2go');
    }

    /**
     * The po2goRules will be an array of stdObjects.
     * Each Object should contain a glob pattern and a uri to which the files matching the pattern will be POSTed.
     *
     * @var array $po2goRules
     */
    if (!empty($config_string)) {
        $jsonObject = json_decode($config_string);
        $po2goRules = isset($jsonObject->rules) ? $jsonObject->rules : null;
        $config     = isset($jsonObject->configuration) ? $jsonObject->configuration : null;
    } else {
        $config = array();
    }

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
            $url = false;
            if (isset($ruleSet->url) && $ruleSet->url) {
                $url = $ruleSet->url;

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
                        $extraction     = Unzipper::flatExtract($file);
                        $result['post'] = false;
                        if ($extraction) {
                            $tmpDirName = basename($file, '.zip') . '_TMP';
                            $boundary   = uniqid();
                            $post       = Poster::buildMultiPartFile($tmpDirName, $boundary);
                            Cleaner::removeUnzippedFiles($tmpDirName);
                            $result['post'] = Poster::curlMultiPartData($url, $post, $boundary, $curlOpts);
                        }
                        break;
                    default:                             // Not an Archive.
                        // POST the file:
                        $result['post'] = Poster::curlPostRawData($url, file_get_contents($file), $curlOpts);
                        break;
                }
            }

            $path = false;
            if (isset($ruleSet->local_path)) {
                $path = $ruleSet->local_path ?: false;
            }

            if ($path) {
                if ( ! is_array($result)) {
                    $result = array();
                }
                $result['transfer'] = Mover::transferFileLocally($path, $file);
            }

            if (false === $result || (is_array($result) && ((isset($result['post']) && false === $result['post']) || (isset($result['transfer']) && false === $result['transfer'])))) {
                // Log the failure, check the age, log again if too old files, then go to the next file.
                $logLevel = 'ERROR';
                // If the file is considered "Severely Old" log to a critical file level, so we will be notified by the log monitor cron.
                $old = SEVERELY_OLD;
                if ( ! empty($config)) {
                    $old = $config->old ? ($config->old * ONE_HOUR) : SEVERELY_OLD;
                }
                if (fileAge($file) > $old) {
                    $logLevel = 'CRITICAL';
                }

                $message = 'FILE: ' . $file . ' -- delivery failure --' . PHP_EOL;
                if (is_array($result) && $result['post'] === false) {
                    $message .= 'Failed to POST to ' . $url . '. File will remain in place.' . PHP_EOL;
                }
                if (is_array($result) && isset($result['transfer']) && $result['transfer'] === false) {
                    $message .= 'Failed to MOVE to ' . $path . '. File will remain in place.' . PHP_EOL;
                }
                if ( ! is_array($result)) {
                    $message .= 'No delivery route set. File will remain in place.' . PHP_EOL;
                }
                $message  .= '--------------';
                $fileName = str_replace('/', '_', $directory) . '.log';
                Logger::logMessage($message, $fileName, $logLevel);
                continue;
            }

            // Log results.
            $message  = PHP_EOL . "\t" . 'RESULT for FILE (' . $file . '): ' . PHP_EOL . print_r($result,
                    true) . PHP_EOL;
            $fileName = str_replace('/', '_', $directory);
            Logger::logMessage($message, $fileName . '.log', 'INFO');

            // TODO: Read the response and determine if the file should be archived or not.
            // There may be times where we had a successful POST, but we don't have the file and will need to rePOST.
            //
            // Resolved: File Splitter on Gateway response is being read and dealt with m=by the Poster class.
            // There may be other scenarios to be dealt with.

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
