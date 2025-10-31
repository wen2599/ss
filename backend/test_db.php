<?php
// backend/test_db.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "--- Database Connection Test ---\n";

// Load environment variables using the shared loader
require_once __DIR__ . '/env_loader.php';
// We must set the path inside this script as well, because env_loader is now incorrect until submitted.
$env_path = __DIR__ . '/.env';
if (!file_exists($env_path)) {
    die("Error: .env file not found at " . $env_path);
}
$env_vars = load_env();

if (empty($env_vars)) {
    die("Error: .env file could not be loaded or is empty. Please check permissions.\n");
}

echo "Environment variables loaded successfully.\n";

// Get database credentials from the returned array
$db_host = $env_vars['DB_HOST'] ?? null;
$db_user = $env_vars['DB_USER'] ?? null;
$db_pass = $env_vars['DB_PASSWORD'] ?? null;
$db_name = $env_vars['DB_NAME'] ?? null;

// Display the loaded credentials for verification (except password)
echo "DB_HOST: " . ($db_host ? $db_host : "NOT SET") . "\n";
echo "DB_USER: " . ($db_user ? $db_user : "NOT SET") . "\n";
echo "DB_NAME: " . ($db_name ? $db_name : "NOT SET") . "\n";
echo "DB_PASSWORD is " . ($db_pass ? "set (hidden for security)" : "NOT SET") . "\n";

if (!$db_host || !$db_user || !$db_pass || !$db_name) {
    die("\nError: One or more database credentials are not set in your .env file.\n");
}

echo "\nAttempting to connect to the database...\n";

// Create a new database connection
// The '@' suppresses the default PHP warning so we can handle errors cleanly.
@$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check for connection errors and provide a detailed message
if ($conn->connect_error) {
    echo "\n--- CONNECTION FAILED ---\n";
    die("Error Details: " . $conn->connect_error . "\n");
}

echo "\n--- CONNECTION SUCCESSFUL ---\n";
echo "Database connected successfully!\n";

// Close the connection
$conn->close();

echo "\n--- Test Finished ---\n";
