<?php
// backend/env_loader.php

if (!function_exists('load_env')) {
    /**
     * Finds and loads environment variables from a .env file in the project root.
     *
     * @return array An associative array of the loaded environment variables.
     */
    function load_env() {
        static $env_vars = null;
        if ($env_vars !== null) {
            return $env_vars;
        }

        // Start from the directory of this file and go up until we find .env
        $current_dir = __DIR__;
        $root_dir = null;

        // Go up a maximum of 5 levels to find the .env file
        for ($i = 0; $i < 5; $i++) {
            if (file_exists($current_dir . '/.env')) {
                $root_dir = $current_dir;
                break;
            }
            $current_dir = dirname($current_dir);
        }

        if ($root_dir === null) {
            // If .env is not found, we cannot proceed.
            // This helps in immediate and clear error reporting.
            die(json_encode([
                'success' => false,
                'message' => 'CRITICAL: .env file could not be found in project root.',
            ]));
        }

        $env_path = $root_dir . '/.env';

        if (!is_readable($env_path)) {
            die(json_encode([
                'success' => false,
                'message' => 'CRITICAL: .env file found but is not readable.',
                'details' => 'Path: ' . $env_path
            ]));
        }

        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env_vars = [];

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                // Trim whitespace and quotes from the value
                $value = trim($value);
                if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
                    $value = substr($value, 1, -1);
                }

                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;

                $env_vars[$name] = $value; 
            }
        }
        return $env_vars;
    }
}
