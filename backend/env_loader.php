<?php
// backend/env_loader.php

if (!function_exists('load_env')) {
    /**
     * Loads environment variables from the .env file located in the same directory.
     *
     * This function reads the .env file, parses its key-value pairs, and does two things:
     * 1. It populates the environment using putenv(), making variables available to getenv().
     * 2. It returns all loaded variables as an associative array.
     *
     * @return array An associative array of the loaded environment variables.
     */
    function load_env() {
        // Use a static variable to cache the loaded variables and prevent reprocessing.
        static $env_vars = null;

        // If already loaded, return the cached array immediately.
        if ($env_vars !== null) {
            return $env_vars;
        }

        $dotenv_path = __DIR__ . '/.env';

        if (!file_exists($dotenv_path) || !is_readable($dotenv_path)) {
            error_log('CRITICAL: .env file not found or is not readable at ' . $dotenv_path);
            return []; // Return an empty array on failure.
        }

        $lines = file($dotenv_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            error_log('CRITICAL: Failed to read the .env file at ' . $dotenv_path);
            return []; // Return an empty array on failure.
        }

        $loaded_vars = [];
        foreach ($lines as $line) {
            // Skip comments and invalid lines.
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes from the value, if any.
            if ((substr($value, 0, 1) == '\'' && substr($value, -1) == '\'') || (substr($value, 0, 1) == '"' && substr($value, -1) == '"')) {
                $value = substr($value, 1, -1);
            }

            // 1. Populate the environment for getenv().
            putenv("{$name}={$value}");
            // 2. Also populate PHP's superglobals.
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            // 3. Store in the array to be returned.
            $loaded_vars[$name] = $value;
        }

        // Cache the result in the static variable.
        $env_vars = $loaded_vars;

        return $env_vars;
    }
}

// For scripts that just `require` this file and expect getenv() to work,
// we must automatically call the function. This ensures compatibility with
// files like `db_connector.php`.
load_env();
