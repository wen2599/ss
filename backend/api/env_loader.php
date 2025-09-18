<?php
// backend/api/env_loader.php

/**
 * A lightweight, dependency-free .env file loader.
 *
 * This function reads a .env file, parses its contents, and loads the
 * variables into PHP's environment using `$_ENV` and `$_SERVER`.
 * This removes the need for the `phpdotenv` library and Composer.
 *
 * @param string $path The full path to the .env file.
 * @return void
 * @throws Exception if the file cannot be read.
 */
function load_env(string $path): void
{
    if (!is_readable($path)) {
        throw new Exception("FATAL ERROR: Environment file not found or not readable at: {$path}");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new Exception("FATAL ERROR: Could not read environment file at: {$path}");
    }

    foreach ($lines as $line) {
        // Ignore comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split into key and value
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove surrounding quotes from value
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }

        // Load into the environment
        if (!empty($key)) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
?>
