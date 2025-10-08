<?php
// backend/lib/helpers.php

if (!function_exists('load_env')) {
    /**
     * Loads environment variables from a .env file into getenv(), $_ENV, and $_SERVER.
     *
     * This function is a lightweight, dependency-free alternative to packages like vlucas/phpdotenv.
     * It reads a .env file, parses the key-value pairs, and makes them available
     * through PHP's standard environment variable functions.
     *
     * @param string $path The full path to the .env file.
     */
    function load_env(string $path): void
    {
        if (!is_readable($path)) {
            // Log or handle the error appropriately. For now, we just return.
            error_log("Env file not found or is not readable at {$path}");
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove surrounding quotes from the value
            if (preg_match('/^(["'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Set the environment variable for the current script
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
