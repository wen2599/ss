<?php
// backend/load_env.php

/**
 * Loads environment variables from a .env file.
 * This version uses highly compatible, simple string functions to support very old PHP versions.
 */
function load_environment_variables_compat() {
    // Corrected path: Look for .env in the same directory as this script.
    $env_file_path = dirname(__FILE__) . '/.env';

    if (!file_exists($env_file_path) || !is_readable($env_file_path)) {
        http_response_code(500);
        // Construct a more informative error message
        $error_message = sprintf(
            "[FATAL] Configuration error: Environment file not found or is not readable. Script was looking for it at: %s",
            $env_file_path
        );
        echo $error_message;
        exit;
    }

    $lines = file($env_file_path);
    if ($lines === false) {
        http_response_code(500);
        echo "[FATAL] Configuration error: Could not read environment file.";
        exit;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }

        // Split on the first '=' character
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue; // Skip malformed lines
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // Basic check for valid key
        if (strpos($key, ' ') !== false) {
            continue;
        }

        // Remove surrounding quotes from the value (e.g., "VALUE" or 'VALUE')
        $first_char = substr($value, 0, 1);
        $last_char = substr($value, -1, 1);
        if (strlen($value) > 1 && (($first_char === '"' && $last_char === '"') || ($first_char === "'" && $last_char === "'"))) {
            $value = substr($value, 1, -1);
        }

        // Load the variable into the environment
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Automatically execute the function when this file is included
load_environment_variables_compat();
