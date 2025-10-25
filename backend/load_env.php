<?php
// backend/load_env.php

/**
 * Loads environment variables from a .env file located in the project root.
 * This version uses dirname(__FILE__) for compatibility with older PHP versions (< 5.3).
 */
function load_environment_variables() {
    // Use dirname(__FILE__) instead of __DIR__ for maximum compatibility.
    $env_file_path = dirname(__FILE__) . '/../.env';

    if (!file_exists($env_file_path) || !is_readable($env_file_path)) {
        http_response_code(500);
        // Keep the error message generic for security.
        echo "[FATAL] Configuration error: Environment file not found or is not readable.";
        exit;
    }

    $lines = file($env_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        http_response_code(500);
        echo "[FATAL] Configuration error: Could not read environment file.";
        exit;
    }

    foreach ($lines as $line) {
        // Skip comments
        if (isset($line[0]) && $line[0] === '#') {
            continue;
        }

        // Use a regular expression for robust parsing of KEY="VALUE"
        // This regex handles optional quotes and comments.
        if (preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:'(.*?)'|"(.*?)"|([^#\s]+))\s*$/', $line, $matches)) {
            $key = $matches[1];
            // The value can be in one of three capturing groups, accommodating different quoting styles.
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
