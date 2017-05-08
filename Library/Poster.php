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

    public static function curlMultiPartData($url, $post, $boundary)
    {
        $defaults =[
            CURLOPT_POST           => 1,
            CURLOPT_HEADER         => 0,
            CURLOPT_URL            => $url,
            CURLOPT_FRESH_CONNECT  => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE   => 1,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_HTTPHEADER     => ["Content-Type: multipart/related; boundary={$boundary}"]
        ];

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

    public static function buildMultiPartFile($directory, $boundary)
    {
        $out = '';
        foreach (glob($directory . '/*') as $filename) {
            if (preg_match('/^[^\._].+\.([^\.]+)$/', $filename, $s)) {
                $ext = strtolower($s[1]);
                $out .= "--{$boundary}\r\n";
                if (preg_match('/^(xml|plain|html|csv|txt|tab)$/', $ext)) {
                    // Based upon the extension, we're assuming plain text.
                    $out .= "Content-Type: text/{$ext}\r\n";
                    $out .= "Content-Transfer-Encoding: 8bit\r\n";
                    $out .= 'Content-Disposition: attachment; filename="' . basename($filename) . "\"\r\n";
                    $out .= "\r\n";
                    $out .= file_get_contents($filename);
                } else {
                    // Base64 encode everything else, just in case.
                    $out .= "Content-Type: application/{$ext}\r\n";
                    $out .= "Content-Transfer-Encoding: Base64\r\n";
                    $out .= 'Content-Disposition: attachment; filename="' . basename($filename) . "\"\r\n";
                    $out .= "\r\n";
                    $out .= base64_encode(file_get_contents($filename));
                }
            }  else {
                // There was no extension, we'll assume plain text XML, not as an attachment, just inline.
                $out .= "Content-Type: text/xml\r\n";
                $out .= "\r\n";
                $out .= file_get_contents($filename);
            }
            $out .= "\r\n";
        }

        $out .= "--{$boundary}--";

        return $out;
    }

}
