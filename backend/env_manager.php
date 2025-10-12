<?php

/**
 * Updates or adds a key-value pair in the .env file.
 *
 * This function reads the entire .env file, line by line,
 * and either updates an existing key or adds a new one at the end.
 * It preserves comments and blank lines.
 *
 * @param string $keyToUpdate The environment variable key to update (e.g., 'GEMINI_API_KEY').
 * @param string $newValue The new value to set for the key.
 * @return bool True on success, false on failure.
 */
function update_env_file($keyToUpdate, $newValue) {
    $envPath = __DIR__ . '/.env';

    if (!file_exists($envPath) || !is_readable($envPath) || !is_writable($envPath)) {
        error_log(".env file does not exist or is not readable/writable at: {$envPath}");
        return false;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        error_log("Failed to read the .env file.");
        return false;
    }

    $keyFound = false;
    $newLines = [];

    foreach ($lines as $line) {
        // Check if the line contains the key we want to update.
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            $newLines[] = $line; // Preserve comments and empty lines.
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        if (trim($key) === $keyToUpdate) {
            // Update the line with the new value.
            $newLines[] = trim($key) . "=\"{$newValue}\"";
            $keyFound = true;
        } else {
            $newLines[] = $line; // Keep other keys as they are.
        }
    }

    // If the key was not found in the file, add it to the end.
    if (!$keyFound) {
        $newLines[] = "{$keyToUpdate}=\"{$newValue}\"";
    }

    // Write the updated content back to the .env file.
    if (file_put_contents($envPath, implode("\n", $newLines)) === false) {
        error_log("Failed to write updated content to the .env file.");
        return false;
    }

    return true;
}

?>
