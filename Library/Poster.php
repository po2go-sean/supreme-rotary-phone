<?php
/**
 * Created by PhpStorm.
 * User: sprunka
 * Date: 5/5/17
 * Time: 12:34 PM
 */

namespace FileMover\Library;


use Curl\Curl;

class Poster
{

    const LOG_NAME = 'cURL.log';

    /**
     * Send a POST request using cURL
     *
     * @param string $url  Target URL
     * @param string $post Raw Text Data to POST
     * @param array  $curlOpts
     *
     * @return bool|string
     */
    public static function curlPostRawData($url, $post = null, array $curlOpts=[])
    {
        return self::doPost($url,$post,false,$curlOpts);
    }

    /**
     * @param string $url      Target URL
     * @param string $post     Raw Multipart Text Data
     * @param string $boundary Boundary marker.
     * @param array  $curlOpts
     *
     * @return bool|string
     */
    public static function curlMultiPartData($url, $post, $boundary, array $curlOpts = [])
    {
        return self::doPost($url,$post, $boundary, $curlOpts);
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

    /**
     * @param string            $url
     * @param array|string|null $post
     * @param bool|string       $multipart
     * @param array             $options
     *
     * @return bool|string
     */
    protected static function doPost($url, $post = null, $multipart = false, array $options = [])
    {
        //Currently the only configurable option will be $options['timeout'] which will default to 2 minutes if not specified.
        $curl = new Curl();
        $curl->setOpt(CURLOPT_HEADER, 0);
        $curl->setOpt(CURLOPT_FRESH_CONNECT, 1);
        $curl->setOpt(CURLOPT_RETURNTRANSFER, 1);
        $curl->setOpt(CURLOPT_FORBID_REUSE, 1);
        $curl->setOpt(CURLOPT_TIMEOUT, $options['timeout'] ?? 180);    // Back down to 2 minutes, since it is now configurable..
        if (false !== $multipart) {
            $curl->setOpt(CURLOPT_HTTPHEADER, ["Content-Type: multipart/related; boundary={$multipart}"]);
        }
        $curl->post($url, $post);

        $result = ($curl->response !== null) ? $curl->response : false;

        // Log Results either way:

        $message = PHP_EOL . "\t" . 'Status Code  : ' . $curl->http_status_code;
        $message .= PHP_EOL . "\t" . 'Response  : ' . $curl->response;
        $message .= PHP_EOL . "\t" . 'POST URL  : ' . $url;
        Logger::logMessage($message, self::LOG_NAME, 'DEBUG');

        // Log Errors
        if ($curl->error) {
            $message = PHP_EOL . "\t" . 'cURL Error: ' . $curl->curl_error_message . "({$curl->error_code})";
            $message .= PHP_EOL . "\t" . 'Response  : ' . $curl->response;
            $message .= PHP_EOL . "\t" . 'POST URL  : ' . $url;
            Logger::logMessage($message, self::LOG_NAME, 'ERROR');
            $result = false;
        }

        // Check to see if the response is JSON that may contain one or more status codes.
        // This is very specific to PO2Go's doc splitter.
        if (strpos($curl->response, '{') === 0) {
            $json_response = json_decode($curl->response);
            if (null !== $json_response) {
                if (null !== $json_response->count && null !== $json_response->docs && $json_response->count > 0 && is_array($json_response->docs)) {
                    $message = '';
                    foreach ($json_response->docs as $doc) {
                        if (null === $doc->status || ($doc->status < 200 || $doc->status > 399)) {
                            $result  = false;
                            $message .= PHP_EOL . "\t" . 'POST response contained a document with status "' . $doc->status . '". Failing POST. ';
                        }
                    }
                    if ('' !== $message) {
                        $message .= PHP_EOL . "\t" . 'Response  : ' . $curl->response;
                        $message .= PHP_EOL . "\t" . 'POST URL  : ' . $url;
                        Logger::logMessage($message, self::LOG_NAME, 'ERROR');
                    }
                }
            }
        }

        return $result;
    }
}
