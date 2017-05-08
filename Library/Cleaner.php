<?php
/**
 * Created by PhpStorm.
 * User: sprunka
 * Date: 5/5/17
 * Time: 12:37 PM
 */

namespace FileMover\Library;


class Cleaner
{

    /**
     * @param string $path
     */
    public static function removeUnzippedFiles($path)
    {
        foreach (glob($path . '/*') as $file) {
            unlink($file);
        }

        rmdir($path);
    }
}
