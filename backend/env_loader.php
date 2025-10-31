<?php
// backend/env_loader.php

if (!function_exists('load_env')) {
    function load_env() {
        // Use a static variable to ensure this runs only once
        static $env_loaded = false;
        if ($env_loaded) {
            return;
        }

        // Look for .env in the same directory as this file
        $env_path = __DIR__ . '/.env';
        if (!file_exists($env_path) || !is_readable($env_path)) {
            // For CLI, output to stderr
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, "Error: .env file not found or is not readable at {$env_path}\n");
            } else { // For web, send a generic error
                http_response_code(500);
                die(json_encode(['error' => 'Internal server configuration error.']));
            }
            exit(1);
        }

        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim(trim($value), '"'); // Trim quotes

                // Set environment variable using putenv, $_ENV, and $_SERVER
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
        $env_loaded = true;
    }
}

load_env();
