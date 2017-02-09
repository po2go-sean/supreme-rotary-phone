<?php
// Explicitly set the current TimeZone to be used.
// If it is already set in the php.ini, GREAT, keep it!
// If not, set it to UTC.
$tz = ini_get('date.timezone')?:'UTC';
ini_set('date.timezone', $tz);

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
    $po2goRules = json_decode(file_get_contents($directory . '/.po2go'))->rules;

    // Step through each rule set.
    foreach ($po2goRules as $ruleSet) {

        // Glob will locate files that match the pattern supplied
        foreach (glob($directory . '/' . $ruleSet->pattern) as $file) {

            // POST the file:
            $result = curlPostRawData($ruleSet->url, file_get_contents($file));


            // If the cURL POST responded with an error $result is false.
            if (!$result) {
                // TODO: Log failure, then go to the next file.
                continue;
            }

            // TODO: Replace This with a logger.
            echo 'RESULT for FILE (' . $file . '): ' . PHP_EOL;
            var_dump($result);


            // Touch the file to update it's timestamp, until we have an archival system.
            touch($file);


            // TODO: Read the response and determine if the file should be archived or not.

            // TODO: Add a post-POST archival utility.

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
        // TODO: Log Errors
        trigger_error(curl_error($ch));
    }
    curl_close($ch);

    return $result;
}
