<?php
// backend/bootstrap.php

// Load helper functions, including our custom environment loader
require_once __DIR__ . '/lib/helpers.php';

// Define the project root as two levels up from the current directory
// This correctly points to the root where the .env file is located, outside the web directory.
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}

// Load environment variables from the .env file at the project root
$dotenv_path = PROJECT_ROOT . '/.env';
if (file_exists($dotenv_path)) {
    load_env($dotenv_path);
} else {
    // This provides a clear error if the .env file is missing.
    die("Error: .env file not found at {$dotenv_path}. Please ensure it exists.");
}

// --- Global Database Connection ---

// Function to get a database connection.
function get_db_connection() {
    static $conn = null; // Static variable to hold the connection

    if ($conn === null) {
        // Connection details from environment variables.
        $db_host = getenv('DB_HOST');
        $db_user = getenv('DB_USER');
        $db_pass = getenv('DB_PASS');
        $db_name = getenv('DB_NAME');

        // Establish the connection.
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

        // Check for connection errors.
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            // In a real app, you might want a more graceful exit.
            die("Database connection failed."); 
        }

        // Set character set to utf8mb4 for full Unicode support.
        $conn->set_charset("utf8mb4");
    }

    return $conn;
}

// Establish the global connection variable for the application to use.
$db = get_db_connection();

?>