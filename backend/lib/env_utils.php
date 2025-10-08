<?php
// backend/lib/env_utils.php

/**
 * Safely updates a key-value pair in the .env file.
 *
 * This function reads the .env file, finds the line with the specified key,
 * and replaces it with the new value. If the key does not exist, it appends
 * it to the end of the file. It preserves comments and file structure.
 *
 * @param string $key The environment variable key to update.
 * @param string $value The new value for the environment variable.
 * @return bool True on success, false on failure.
 */
function update_env_file(string $key, string $value): bool
{
    $env_path = __DIR__ . '/../../.env';

    if (!file_exists($env_path) || !is_readable($env_path) || !is_writable($env_path)) {
        error_log(".env file does not exist or is not readable/writable.");
        return false;
    }

    $content = file_get_contents($env_path);
    if ($content === false) {
        return false;
    }

    // To prevent issues with special characters in the value, escape them for the regex.
    // However, since we are setting a simple key, it's better to construct the line directly.
    // The value itself should not be part of the regex.
    $key_for_regex = preg_quote($key, '/');

    // Regex to find the key at the beginning of a line.
    $pattern = "/^{$key_for_regex}=.*/m";
    $new_line = "{$key}=\"{$value}\""; // Always wrap value in quotes for safety

    // Check if the key exists
    if (preg_match($pattern, $content)) {
        // Key exists, replace it
        $new_content = preg_replace($pattern, $new_line, $content);
    } else {
        // Key does not exist, append it
        $new_content = rtrim($content) . "\n" . $new_line;
    }

    // Atomically write the new content back to the file
    return file_put_contents($env_path, $new_content, LOCK_EX) !== false;
}
