<?php
// backend/utils/config_loader.php

/**
 * Manually loads environment variables from a .env file.
 * This function does not depend on any external libraries.
 *
 * @param string $path The path to the .env file.
 */
function load_env($path) {
    if (!is_readable($path)) {
        // Log this error, as it\'s critical
        error_log("Config Loader Error: .env file not found or is not readable at path: " . $path);
        // Optionally, you could throw an exception or trigger a fatal error
        // throw new \RuntimeException(sprintf(\'Could not find or read the .env file at %s.\', $path));
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), \'#\') === 0) {
            continue;
        }

        // Split into key and value
        list($name, $value) = explode(\'=\', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove surrounding quotes from value
        if (preg_match(\'/^"(.*)"$/\', $value, $matches)) {
            $value = $matches[1];
        } elseif (preg_match(\'/\'^(.*)\'$/\', $value, $matches)) {
            $value = $matches[1];
        }

        // Set environment variables
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf(\'%s=%s\', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
