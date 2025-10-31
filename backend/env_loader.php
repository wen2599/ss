<?php
// backend/env_loader.php

if (!function_exists('load_env')) {
    function load_env() {
        static $env_vars = null;
        if ($env_vars !== null) {
            return $env_vars;
        }

        $env_path = __DIR__ . '/.env';
        
        if (!file_exists($env_path) || !is_readable($env_path)) {
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, "Error: .env file not found or is not readable at {$env_path}\n");
            }
            return [];
        }

        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env_vars = [];

        foreach ($lines as $line) {
            // Debugging: Show raw line if it contains DB_PASSWORD
            if (strpos($line, 'DB_PASSWORD') !== false) {
                error_log("DEBUG env_loader: Processing raw line: " . $line);
            }

            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim(trim($value), '"'); // Trim quotes

                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;

                $env_vars[$name] = $value; 

                // Debugging: Show parsed name and value for DB_PASSWORD
                if ($name === 'DB_PASSWORD') {
                    error_log("DEBUG env_loader: Parsed DB_PASSWORD -> Name: '{$name}', Value: '{$value}'");
                }
            }
        }
        return $env_vars;
    }
}
