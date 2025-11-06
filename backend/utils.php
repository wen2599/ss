<?php
// backend/utils.php

if (!function_exists('get_all_headers')) {
    /**
     * A polyfill for the getallheaders() function, which is not available in all environments (e.g., FPM/FastCGI).
     *
     * This function manually parses the $_SERVER superglobal to extract all HTTP request headers.
     *
     * @return array An associative array of the request headers.
     */
    function get_all_headers() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                // Converts HTTP_HOST into Host
                // Converts HTTP_X_CUSTOM_HEADER into X-Custom-Header
                $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$header_name] = $value;
            } elseif (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                 // These headers are not prefixed with HTTP_ by the web server
                $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
                $headers[$header_name] = $value;
            }
        }
        return $headers;
    }
}
?>