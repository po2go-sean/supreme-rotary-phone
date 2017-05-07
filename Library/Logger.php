<?php
/**
 * Created by PhpStorm.
 * User: sprunka
 * Date: 5/5/17
 * Time: 12:27 PM
 */

namespace FileMover\Library;


class Logger
{
    const definedLevels = [
        'EMERGENCY' => 'SEVERE',    // system is unusable
        'ALERT'     => 'SEVERE',    // action must be taken immediately
        'CRITICAL'  => 'SEVERE',    // critical conditions
        'ERROR'     => 'ERROR',     // error conditions
        'WARNING'   => 'MINOR',     // warning conditions
        'NOTICE'    => 'MINOR',     // normal, but significant, condition
        'INFO'      => 'INFO',      // informational message
        'DEBUG'     => 'INFO'       // debug-level message
    ];

    /**
     * Creates 2 log files.
     * One Unified that would contain all messages of any level for easier debugging
     * On "explicit" that has logs grouped by severity based upon level.
     *
     * @param string $message
     * @param string $fileName
     * @param string $level
     */
    public static function logMessage($message, $fileName = 'FileMover.log', $level = 'INFO')
    {
        $path = __DIR__ . '/../var/logs/';

        if (!array_key_exists($level, self::definedLevels)) {
            $level = 'INFO';
        }

        $now = new \DateTime();

        $message = $now->format('Y-m-d H:i:s O') . ' [' . $level . ']: ' . $message . PHP_EOL;

        $explicitFilePath = $path . self::definedLevels[$level] . '-' . $fileName;
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

}
