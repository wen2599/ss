<?php
// backend/bootstrap.php

// --- Environment Variable Loading ---
// Simple .env file parser.
function load_env($path) {
    if (!file_exists($path)) {
        return;
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

// Load .env file from the backend directory
load_env(__DIR__ . '/.env');

// --- Database Connection ---
function get_db_connection() {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // In a real application, you would log this error.
        // For now, we'll just stop the script.
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}

// --- Global Error Handling ---
// Set a basic error handler to catch warnings and notices.
set_error_handler(function($severity, $message, $file, $line) {
    // In a production environment, you would log this to a file.
    // For now, we'll just throw an exception to halt execution.
    throw new ErrorException($message, 0, $severity, $file, $line);
});
