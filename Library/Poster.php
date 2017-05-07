<?php
/**
 * Created by PhpStorm.
 * User: sprunka
 * Date: 5/5/17
 * Time: 12:34 PM
 */

namespace FileMover\Library;


class Poster
{

    const LOG_NAME = 'cURL.log';

    /**
     * Send a POST request using cURL
     *
     * @param string $url  Target URL
     * @param string $post Raw Text Data to POST
     *
     * @return string
     */
    public static function curlPostRawData($url, $post = null)
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
            $message = PHP_EOL . "\t" . 'cURL Error: ' . curl_error($ch);
            $message .= PHP_EOL . "\t" . 'POST URL  : ' . $url;
            Logger::logMessage($message, self::LOG_NAME, 'ERROR');
        }
        curl_close($ch);

        return $result;
    }

    public static function curlMultiPartData($url,$post)
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
            $message = PHP_EOL . "\t" . 'cURL Error: ' . curl_error($ch);
            $message .= PHP_EOL . "\t" . 'POST URL  : ' . $url;
            Logger::logMessage($message, self::LOG_NAME, 'ERROR');
        }
        curl_close($ch);

        return $result;

    }

}
