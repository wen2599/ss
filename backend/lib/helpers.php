<?php
// backend/lib/helpers.php

if (!function_exists('send_json_response')) {
    /**
     * Sends a JSON response with a specific HTTP status code.
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $status_code The HTTP status code to send.
     */
    function send_json_response($data, $status_code = 200) {
        http_response_code($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('log_request')) {
    /**
     * Logs an incoming request to a specified file.
     *
     * @param string $log_file The path to the log file.
     */
    function log_request($log_file) {
        $log_message = date('[Y-m-d H:i:s]') . " " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}
