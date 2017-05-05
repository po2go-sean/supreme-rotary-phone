<?php
/**
 * Created by PhpStorm.
 * User: sprunka
 * Date: 5/5/17
 * Time: 12:37 PM
 */

namespace FileMover\Library;


class Mover
{

    /**
     * @param string      $path
     * @param string      $fileName
     * @param null|string $user
     *
     * @return bool
     */
    public static function transferFileLocally($path, $fileName, $user = null): bool
    {
        if ($user) {
            $result = chown($fileName, $user);
            if (!$result) {
                $message = 'Failed to chown.';
                Logger::logMessage($message, 'filemove.log', 'ERROR');

                return $result;
            }
        }

        $result = rename($fileName, $path . '/' . basename($fileName));

        if (!$result) {
            $message = 'Failed to rename.';
            Logger::logMessage($message, 'filemove.log', 'ERROR');

            return $result;
        }

        return true;

    }
}
