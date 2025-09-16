<?php
// backend/api/database.php

// Include the .env loader and load the .env file from the same directory
require_once __DIR__ . '/dotenv.php';
loadDotEnv(__DIR__ . '/.env');

// The config file is still included for other settings, but no longer for DB credentials
require_once __DIR__ . '/config.php';

function getDbConnection() {
    // Retrieve database credentials from environment variables
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS'); // Note: A password can be empty, so we don't check it for existence here.
    $charset = 'utf8mb4';

    // Check if all required environment variables are set
    if ($host === false || $db === false || $user === false || $pass === false) {
        http_response_code(500);
        error_log('Database configuration is incomplete. One or more DB environment variables are not set.');
        echo json_encode(['success' => false, 'message' => 'Server configuration error: Database connection details are missing.']);
        exit();
    }

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (\PDOException $e) {
        // In a real application, you would log this error instead of echoing it.
        error_log('Database Connection Error: ' . $e->getMessage());

        // For this project, we'll send a generic error response to the client.
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please check the server logs for details.']);
        exit();
    }
}
?>
