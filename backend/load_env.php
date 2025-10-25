<?php
// backend/load_env.php

/**
 * Loads environment variables from a .env file.
 * This is a simple, dependency-free implementation for pure PHP projects.
 */
function load_environment_variables() {
    $env_file_path = __DIR__ . '/.env';

    if (!is_readable($env_file_path)) {
        // If the .env file is critical for configuration, it's best to stop execution.
        http_response_code(500);
        // Avoid echoing the full path for security reasons.
        echo "Configuration error: .env file not found or is not readable in the 'backend' directory.";
        exit;
    }

    $lines = file($env_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split into key and value
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove surrounding quotes from the value (e.g., "value" -> value)
        if (strlen($value) > 1 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            $value = substr($value, 1, -1);
        }
        
        // Load the variable into the environment. 
        // putenv() makes it available to getenv().
        // $_ENV and $_SERVER are also populated for direct access.
        if (!empty($key)) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Execute the function to load the variables
load_environment_variables();
