<?php
/**
 * Loads environment variables from a .env file into $_ENV and $_SERVER.
 *
 * This version avoids using putenv() to ensure maximum compatibility
 * with various hosting environments, especially those where putenv()
 * might cause side effects like network resolution issues.
 *
 * @param string $path The full path to the .env file.
 * @return void
 */
function load_env($path) {
    if (!file_exists($path)) {
        // In a real application, you might want to throw an exception here.
        // For this project, we'll just return silently if .env is missing.
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split the line into a key and a value
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes from the value, if they exist
        if (strlen($value) > 1 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            $value = substr($value, 1, -1);
        }

        // Set the environment variable in both $_ENV and $_SERVER.
        // This is the safest way to make them available to the application.
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
