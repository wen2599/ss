<?php
// backend/utils/config_loader.php

/**
 * Loads environment variables from a .env file.
 *
 * This function reads a .env file, parses its contents, and loads the
 * variables into the environment using putenv(), making them accessible
 * via getenv().
 *
 * @param string $path The path to the .env file.
 */
function load_env($path) {
    if (!file_exists($path)) {
        // Log an error or handle the missing file case
        error_log(".env file not found at path: " . $path);
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
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }

        // Load the variable into the environment
        if (!empty($key)) {
            putenv(sprintf('%s=%s', $key, $value));
        }
    }
}

// Construct the path to the .env file in the `backend` directory
$dotenv_path = __DIR__ . '/../.env';
load_env($dotenv_path);
