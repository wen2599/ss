<?php
// backend/bootstrap.php

// Define a project root constant for consistent path resolution.
define('PROJECT_ROOT', dirname(__DIR__));

/**
 * Loads environment variables from a .env file into the application.
 *
 * This function reads a .env file, parses its contents, and loads the
 * variables into PHP's environment using putenv(), $_ENV, and $_SERVER.
 * It will not overwrite existing environment variables.
 *
 * @param string $path The path to the .env file.
 */
function load_env($path) {
    if (!is_readable($path)) {
        // Silently return if the file doesn't exist or isn't readable.
        // The application will rely on server-level environment variables.
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Do not overwrite existing environment variables.
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load the .env file from the backend directory.
load_env(__DIR__ . '/.env');

// Include the Composer autoloader to load third-party libraries.
// This is crucial for libraries like the Telegram Bot SDK.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // If composer autoloader is missing, it's a critical setup error.
    die("Error: Composer autoloader not found. Please run 'composer install' in the 'backend' directory.");
}

// Include application-specific configuration.
// This file contains database credentials, API keys, etc.
require_once __DIR__ . '/config.php';

// Include helper functions.
// This file contains utility functions like get_db_connection().
require_once __DIR__ . '/lib/helpers.php';

?>