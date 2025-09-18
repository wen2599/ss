<?php
function loadEnv($path)
{
    if (!file_exists($path)) {
        // You might want to throw an exception or handle this error more gracefully
        error_log(".env file not found at path: " . $path);
        // Fallback or error out
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error: .env file missing.']);
        exit;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// This file only defines the function. The caller is responsible for invoking it.
?>
