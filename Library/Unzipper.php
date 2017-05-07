<?php
/**
 * Created by PhpStorm.
 * User: sprunka
 * Date: 5/5/17
 * Time: 12:25 PM
 */

namespace FileMover\Library;


class Unzipper
{
    const LOG_NAME = 'Unzipper.log';
    const LOG_CRITICAL = 'CRITICAL';

    public static function extractZipArchive($file)
    {
        $zip = new \ZipArchive();
        $isOpen = $zip->open($file, \ZipArchive::CHECKCONS);

        if (true !== $isOpen) {
            switch ($isOpen) {
                case \ZipArchive::ER_INCONS:
                    Logger::logMessage('Failed to open \'' . $file . '\' -- Zip archive inconsistent.', self::LOG_NAME,
                        self::LOG_CRITICAL);

                    break;
                case \ZipArchive::ER_INVAL:
                    Logger::logMessage('Failed to open \'' . $file . '\' -- Invalid argument.', self::LOG_NAME,
                        self::LOG_CRITICAL);

                    break;
                case \ZipArchive::ER_MEMORY:
                    Logger::logMessage('Failed to open \'' . $file . '\' -- Malloc failure.', self::LOG_NAME,
                        self::LOG_CRITICAL);

                    break;
                case \ZipArchive::ER_NOENT:
                    Logger::logMessage('Failed to open \'' . $file . '\' -- No such file.', self::LOG_NAME,
                        self::LOG_CRITICAL);

                    break;
                case \ZipArchive::ER_NOZIP:
                    Logger::logMessage('Failed to open \'' . $file . '\' -- Not a zip archive.', self::LOG_NAME,
                        self::LOG_CRITICAL);

                    break;
                case \ZipArchive::ER_OPEN:
                    Logger::logMessage('Failed to open \'' . $file . '\' -- Can\'t open file.', self::LOG_NAME,
                        self::LOG_CRITICAL);

                    break;
                case \ZipArchive::ER_READ:
                    Logger::logMessage('Failed to open \'' . $file . '\' -- Read error.', self::LOG_NAME,
                        self::LOG_CRITICAL);

                    break;
                case \ZipArchive::ER_SEEK:
                    Logger::logMessage('Failed to open \'' . $file . '\' -- Seek error.', self::LOG_NAME,
                        self::LOG_CRITICAL);

                    break;
            }
             return false;
        }

        Logger::logMessage('There was no error, proceed.',self::LOG_NAME,'DEBUG');


        $tmpDirName = basename($file,'.zip') . '_TMP';

        if (!@mkdir($tmpDirName) && !is_dir($tmpDirName) ) {
            // Can't make a temporary directory to hold the extracted files.
            Logger::logMessage('Cannot create temporary directory for ' . $file,self::LOG_NAME, self::LOG_CRITICAL);
            return false;
        }




        for($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $fileinfo = pathinfo($filename);
            copy("zip://".$file."#".$filename, $tmpDirName.'/'.$fileinfo['basename']);
        }

        $zip->close();

        return false;

        if (!$isExtracted) {
            Logger::logMessage('Failed extracting ' . $file, self::LOG_NAME, self::LOG_CRITICAL);
            rmdir($tmpDirName);
            return false;
        }

        Logger::logMessage($file . ' has been extracted to ' . $tmpDirName, self::LOG_NAME);
        return $isExtracted;
    }
}
