<?php
// backend/bootstrap.php

// --- Temporary Debugging ---
// This code is for debugging the .env file loading issue.
// It will be removed once the problem is resolved.
$env_path = __DIR__ . '/.env';
if (!is_file($env_path)) {
    die(".env file does not exist at path: " . $env_path);
}
if (!is_readable($env_path)) {
    die(".env file exists but is not readable. Check file permissions. Path: " . $env_path);
}
// --- End of Temporary Debugging ---


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
?>