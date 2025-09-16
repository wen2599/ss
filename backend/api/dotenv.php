<?php
// backend/api/dotenv.php

/**
 * A simple .env file loader.
 *
 * This function reads a .env file, parses it, and loads the variables into
 * the environment using putenv(), $_ENV, and $_SERVER.
 *
 * @param string $path The path to the .env file.
 */
function loadDotEnv(string $path): void
{
    if (!is_readable($path)) {
        // Silently fail if the file doesn't exist or is not readable.
        // This allows the application to run in environments where .env files are not used (e.g., production with server-level env vars).
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split into name and value
        if (strpos($line, '=') === false) {
            continue; // Skip lines without an equals sign
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove quotes from value
        if (preg_match('/^"(.*)"$/', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
            $value = $matches[1];
        }

        // Set the environment variable if it's not already set
        if (!getenv($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
