<?php
// backend/check_db.php
// A diagnostic script to check the database connection.
// To use, run from the command line: php check_db.php

// Set context to command-line interface
if (php_sapi_name() !== 'cli') {
    die("This script is intended for command-line use only.");
}

echo "--- Database Connection Diagnostic Tool ---\n";

// Load environment variables
require_once __DIR__ . '/load_env.php';

// --- Configuration ---
$db_host = $_ENV['DB_HOST'] ?? 'not set';
$db_port = $_ENV['DB_PORT'] ?? 'not set';
$db_database = $_ENV['DB_DATABASE'] ?? 'not set';
$db_user = $_ENV['DB_USER'] ?? 'not set';
$db_password_is_set = !empty($_ENV['DB_PASSWORD']);

echo "Attempting to connect with the following configuration:\n";
echo "Host: $db_host\n";
echo "Port: $db_port\n";
echo "Database: $db_database\n";
echo "User: $db_user\n";
echo "Password Set: " . ($db_password_is_set ? 'Yes' : 'No') . "\n\n";

if ($db_host === 'not set' || $db_database === 'not set' || $db_user === 'not set' || !$db_password_is_set) {
    echo "Error: One or more required database environment variables (DB_HOST, DB_DATABASE, DB_USER, DB_PASSWORD) are not set in your .env file.\n";
    exit(1);
}

// --- Connection Test ---
try {
    // Check for PDO extension
    if (!extension_loaded('pdo_mysql')) {
        echo "Error: The 'pdo_mysql' PHP extension is not enabled. This is required to connect to MySQL.\n";
        exit(1);
    }

    $pdo = new PDO(
        "mysql:host=" . $db_host . ";port=" . $db_port . ";dbname=" . $db_database . ";charset=utf8mb4",
        $db_user,
        $_ENV['DB_PASSWORD']
    );

    // If we reach here, the connection was successful
    echo "SUCCESS: Database connection was successful!\n";
    $pdo = null; // Close the connection

} catch (PDOException $e) {
    // If the connection fails, an exception is thrown
    echo "FAILURE: Could not connect to the database.\n";
    echo "Error Message: " . $e->getMessage() . "\n\n";
    echo "Common things to check:\n";
    echo "1. Is the MySQL server running?\n";
    echo "2. Are the DB_HOST, DB_PORT, DB_DATABASE, DB_USER, and DB_PASSWORD values in your 'backend/.env' file correct?\n";
    echo "3. Is the user '$db_user' allowed to connect from this server's IP address?\n";
    echo "4. Is a firewall blocking the connection?\n";
    exit(1);
}

echo "\n--- Diagnostic Tool Finished ---\n";
