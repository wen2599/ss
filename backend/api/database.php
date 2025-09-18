<?php
// backend/api/database.php

// The config file now contains all necessary credentials and settings.
require_once __DIR__ . '/config.php';

function getDbConnection() {
    // Retrieve database credentials from constants defined in config.php
    $host = defined('DB_HOST') ? DB_HOST : null;
    $db   = defined('DB_NAME') ? DB_NAME : null;
    $user = defined('DB_USER') ? DB_USER : null;
    $pass = defined('DB_PASS') ? DB_PASS : null;
    $charset = 'utf8mb4';

    // Check if all required constants are defined
    if (is_null($host) || is_null($db) || is_null($user) || is_null($pass)) {
        http_response_code(500);
        error_log('Database configuration is incomplete. One or more DB constants are not defined in config.php.');
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
        require_once __DIR__ . '/error_logger.php';
        log_error('Database Connection Error: ' . $e->getMessage());

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Check backend/logs/error.log for details.']);
        exit();
    }
}
?>
