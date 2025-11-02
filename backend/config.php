<?php
// backend/config.php

// --- Error Reporting ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Timezone ---
date_default_timezone_set('Asia/Shanghai');

// --- Load .env file using our custom loader ---
require_once __DIR__ . '/utils/config_loader.php';
$dotenv_path = __DIR__ . '/.env'; // Assuming .env is in the project root
load_env($dotenv_path);

// --- Database Connection (PDO) ---
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: '';
$db_user = getenv('DB_USER') ?: '';
$db_pass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    error_log("Database connection successful.");
} catch (\PDOException $e) {
    // In a real app, you would log this error and show a generic message
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    // Avoid echoing sensitive info, even in a generic message
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error - DB Connection Failed']);
    exit;
}
