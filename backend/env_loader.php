<?php
// backend/env_loader.php

if (!function_exists('load_env')) {
    function load_env() {
        echo "DEBUG: env_loader.php: load_env() called.\n";
        // Use a static variable to ensure this runs only once
        static $env_loaded = false;
        if ($env_loaded) {
            echo "DEBUG: env_loader.php: env already loaded, returning.\n";
            return;
        }

        // Look for .env in the same directory as this file
        $env_path = __DIR__ . '/.env';
        echo "DEBUG: env_loader.php: Attempting to load .env from: {$env_path}\n";
        
        if (!file_exists($env_path) || !is_readable($env_path)) {
            echo "DEBUG: env_loader.php: .env file NOT found or NOT readable at {$env_path}\n";
            // For CLI, output to stderr
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, "Error: .env file not found or is not readable at {$env_path}\n");
            }
            // Do NOT exit here. Let setup_database.php handle the ultimate error
            // exit(1); // Removed to allow setup_database.php to show its error
            return; // Return silently if not found, let the caller handle it
        }

        echo "DEBUG: env_loader.php: .env file found and readable.\n";
        $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        echo "DEBUG: env_loader.php: Successfully read " . count($lines) . " lines from .env.\n";

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
                // echo "DEBUG: Set {$name} = " . ($_ENV[$name] ?? 'null') . "\n"; // Too verbose, enable if needed
            }
        }
        $env_loaded = true;
        echo "DEBUG: env_loader.php: Finished processing .env file.\n";
        echo "DEBUG: env_loader.php: DB_HOST in _ENV after loading: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
    }
}

load_env();
