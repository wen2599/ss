<?php
// backend/bootstrap.php

// --- Error Reporting Configuration ---
// Enable all error reporting for development.
ini_set('display_errors', 0); // DO NOT display errors directly to the browser
ini_set('log_errors', 1);    // DO log errors to the server error log
error_reporting(E_ALL);      // Report all PHP errors

// --- CORS (Cross-Origin Resource Sharing) Handling ---
// Allow requests from any origin for debugging.
// For production, you should restrict this to your specific frontend domain.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true"); // Allow cookies to be sent
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization"); // Added Authorization

// Handle preflight requests (the browser sends an OPTIONS request first)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Environment Variable Loading ---
require_once __DIR__ . '/load_env.php';

// --- Database Configuration ---
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? '');
define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? '');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

// --- Database Connection Initialization ---
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Log the error for debugging (consider a more robust logging mechanism for production)
    error_log("Database connection failed: " . $e->getMessage());
    // Return a 500 Internal Server Error to the client
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed.']);
    exit();
}

// --- Session Handling ---
// Start the session only if it's not already started.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
