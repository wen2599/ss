<?php
// backend/env_loader.php

if (!function_exists('load_env')) {
    function load_env() {
        static $env_loaded = false;
        if ($env_loaded) {
            return;
        }

        $dotenv_path = '';
        $current_dir = __DIR__;
        for ($i = 0; $i < 5; $i++) {
            $potential_path = $current_dir . '/.env';
            if (file_exists($potential_path)) {
                $dotenv_path = $potential_path;
                break;
            }
            // Go up one level
            $parent_dir = dirname($current_dir);
            if ($parent_dir === $current_dir) { // Reached the filesystem root
                break;
            }
            $current_dir = $parent_dir;
        }

        if (empty($dotenv_path) || !is_readable($dotenv_path)) {
            error_log('CRITICAL: .env file could not be found or is not readable.');
            return;
        }

        $lines = file($dotenv_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            error_log('CRITICAL: Failed to read the .env file.');
            return;
        }

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.*?)?\s*$/', $line, $matches)) {
                $name = $matches[1];
                $value = isset($matches[2]) ? $matches[2] : '';

                // Trim whitespace and quotes from the value
                $value = trim($value);
                if ((substr($value, 0, 1) == '\'' && substr($value, -1) == '\'') || (substr($value, 0, 1) == '"' && substr($value, -1) == '"')) {
                    $value = substr($value, 1, -1);
                }

                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }

        $env_loaded = true;
    }
}

// Automatically load the environment variables when this file is included
load_env();
