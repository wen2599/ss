<?php
// backend/lib/helpers.php

if (!function_exists('load_env')) {
    /**
     * Loads environment variables from a .env file.
     *
     * @param string $path The full path to the .env file.
     */
    function load_env(string $path): void
    {
        if (!is_readable($path)) {
            error_log("Env file not found or is not readable at {$path}");
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove surrounding quotes from the value (e.g., "value" -> value)
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Set the environment variable
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('send_json_response')) {
    /**
     * Sends a JSON response and exits the script.
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $status_code The HTTP status code.
     */
    function send_json_response($data, int $status_code = 200): void
    {
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
    function log_request(string $log_file): void
    {
        $log_message = date('[Y-m-d H:i:s]') . " " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . "\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }
}