<?php
// backend/api/db.php

// Set a default timezone
date_default_timezone_set('UTC');

// --- Environment Variable Loader ---
if (!function_exists('load_dot_env')) {
    function load_dot_env($path)
    {
        if (!file_exists($path) || !is_readable($path)) {
            // Silently fail if not found, as env vars might be set by the server
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!empty($name)) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// --- Database Connection ---

// Load .env from the root directory (two levels up from here)
$dotenv_path = __DIR__ . '/../../.env';
load_dot_env($dotenv_path);

$db_host = getenv('DB_HOST');
$db_name = getenv('DB_DATABASE');
$db_user = getenv('DB_USERNAME');
$db_pass = getenv('DB_PASSWORD');

// Create a new database connection
$db_connection = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check for connection errors
if ($db_connection->connect_error) {
    // In a real API, you might log this error instead of echoing it.
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// Set charset to utf8mb4
$db_connection->set_charset("utf8mb4");

// This script doesn't output anything on its own.
// It provides the $db_connection variable to any script that includes it.
