<?php

/**
 * Updates a specific key in the .env file.
 *
 * This function reads the .env file, line by line, and updates the value
 * for the specified key. It preserves comments and blank lines.
 *
 * @param string $keyToUpdate The environment variable key to update (e.g., 'GEMINI_API_KEY').
 * @param string $newValue    The new value for the key.
 * @return bool True on success, false on failure.
 */
function update_env_file($keyToUpdate, $newValue) {
    $envPath = __DIR__ . '/.env';

    if (!file_exists($envPath) || !is_readable($envPath) || !is_writable($envPath)) {
        error_log("Error: .env file is missing or not readable/writable at {$envPath}");
        return false;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    $newLines = [];
    $keyFound = false;

    foreach ($lines as $line) {
        // Trim the line to handle whitespace and check if it's a key-value pair.
        if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
            $newLines[] = $line;
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);

        if ($key === $keyToUpdate) {
            // Update the key with the new value, ensuring it's properly quoted if it contains spaces.
            $newLines[] = "{$keyToUpdate}=\"{$newValue}\"";
            $keyFound = true;
        } else {
            $newLines[] = $line;
        }
    }

    // If the key was not found, add it to the end of the file.
    if (!$keyFound) {
        $newLines[] = "{$keyToUpdate}=\"{$newValue}\"";
    }

    // Write the updated content back to the .env file.
    if (file_put_contents($envPath, implode("\n", $newLines)) === false) {
        error_log("Error: Failed to write to .env file at {$envPath}");
        return false;
    }

    // --- IMPORTANT: Reload environment variables ---
    // Use putenv to update the environment for the currently running script.
    putenv("{$keyToUpdate}={$newValue}");

    return true;
}