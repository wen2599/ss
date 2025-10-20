<?php
// backend/bootstrap.php
// Basic configuration and database connection.

// Load environment variables from .env file
require_once __DIR__ . '/load_env.php';

// --- Database Configuration ---
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

// --- Global Settings ---
// Set the default timezone.
date_default_timezone_set('UTC');

// Enable error reporting for development.
// In a production environment, you should log errors to a file instead.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Database Connection ---
// Establish a connection to the MySQL database using PDO.
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_DATABASE . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD
    );
    // Set PDO to throw exceptions on error.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If the connection fails, terminate the script and display an error.
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// --- Session Management ---
// Start or resume a session.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Headers ---
// Set common security headers.
// Note: These might be better handled by your web server (Nginx/Apache) in production.
header("Content-Security-Policy: default-src 'self'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

?>
