<?php
// config.php
// Handles environment variable loading and database connection.

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- Database Connection ---
// This script will now create and return the PDO connection object.

// Database credentials from environment variables
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? 'lottery_app';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';

// DSN (Data Source Name)
$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Important for security and debugging
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // If the connection fails, we can't do much.
    // The global error handler in init.php will catch this if it's included,
    // otherwise, we output a generic error.
    // This is a critical failure.
    error_log("CRITICAL: Database connection failed in config.php: " . $e->getMessage());
    // Note: We don't echo a JSON error here because the Content-Type header
    // might not have been set yet. The init.php script will handle the user-facing error.
    die('Could not connect to the database.'); // Stop execution immediately
}

// The $pdo variable is now available to any script that includes this config.
// For example, in init.php: require_once __DIR__ . '/config.php';
?>
