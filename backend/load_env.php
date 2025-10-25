<?php
// backend/load_env.php

/**
 * Loads environment variables from a .env file.
 * This script is designed to be included by other PHP files within the backend directory.
 */
function load_environment_variables_compat() {
    // Corrected path: Look for .env in the parent directory (the project root).
    $env_file_path = dirname(__FILE__) . '/../.env';

    if (!file_exists($env_file_path) || !is_readable($env_file_path)) {
        http_response_code(500);
        $error_message = sprintf(
            "[FATAL] Configuration error: Environment file not found or is not readable. The script expected it at: %s",
            realpath(dirname(__FILE__) . '/../')
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

        if (empty($line) || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if (strpos($key, ' ') !== false) {
            continue;
        }

        $first_char = substr($value, 0, 1);
        $last_char = substr($value, -1, 1);
        if (strlen($value) > 1 && (($first_char === '"' && $last_char === '"') || ($first_char === "\'" && $last_char === "\'"))) {
            $value = substr($value, 1, -1);
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Automatically execute the function when this file is included
load_environment_variables_compat();
