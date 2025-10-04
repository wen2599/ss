<?php
// A simple function to load environment variables from a .env file.
function load_env($path) {
    if (!file_exists($path)) {
        // In a production environment, you might want to throw an exception here.
        // For this simple case, we'll allow it to fail silently, and the calling scripts
        // will handle the missing secret.
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}
?>