<?php
// backend/load_env.php

/**
 * Loads environment variables from a .env file located in the project root.
 * This is a simple, dependency-free implementation.
 */
function load_environment_variables() {
    // The .env file is expected to be in the parent directory of this script's location.
    $env_file_path = __DIR__ . '/../.env';

    if (!is_readable($env_file_path)) {
        http_response_code(500);
        // Keep the error message generic for security.
        echo "[FATAL] Configuration error: Environment file not found or is not readable.";
        exit;
    }

    $lines = file($env_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Use a regular expression for more robust parsing of KEY="VALUE"
        if (preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:'(.*?)'|"(.*?)"|([^#\s]+))\s*$/', $line, $matches)) {
            $key = $matches[1];
            // The value can be in one of three capturing groups
            $value = isset($matches[4]) ? $matches[4] : (isset($matches[3]) ? $matches[3] : $matches[2]);

            // Load the variable into the environment
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Automatically execute the function when this file is included
load_environment_variables();
