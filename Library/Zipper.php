<?php
/**
 * Created by PhpStorm.
 * User: sprunka
 * Date: 5/5/17
 * Time: 12:25 PM
 */

namespace FileMover\Library;


class Zipper
{
    /**
     * This will Zip the incoming file, delete the original and optionally move the archive elsewhere.
     * It is possible to archive multiple file by calling this mulitole times, always using the same
     * $archiveFileName
     *
     * @param string      $originalFileName
     * @param null|string $archiveFileName
     */
    static public function archiveFileAsZip($originalFileName, $archiveFileName = null)
    {
        $zip = new \ZipArchive();
        if (null === $archiveFileName) {
            $archiveFileName = $originalFileName . '.zip';
        }

        $message = '';
        if ($zip->open($archiveFileName, \ZipArchive::CREATE) !== true) {
            $message .= 'Unable to create archive "' . $archiveFileName . '" for file "' . $originalFileName . '"';
            $message .= "\t" . 'This file will be re-POSTED if not manually deleted or moved.';
            Logger::logMessage($message, 'Archive.log', 'CRITICAL');

            return;
        }

        $zip->addFile($originalFileName);
        $zip->close();

        $deleted = unlink($originalFileName);

        if ($deleted) {
            $message = 'Original File: ' . $originalFileName . ' deleted.';
            Logger::logMessage($message, 'Archive.log', 'INFO');

            return;
        }
        $message = 'Original File: ' . $originalFileName . ' failed to be deleted.';
        $message .= "\t" . 'This file will be re-POSTED if not manually deleted or moved.';
        Logger::logMessage($message, 'Archive.log', 'CRITICAL');

    }

}
