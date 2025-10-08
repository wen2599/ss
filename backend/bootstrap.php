<?php
// backend/bootstrap.php

// Load helper functions, including our custom environment loader
require_once __DIR__ . '/lib/helpers.php';

// Define the project root based on the current working directory, assuming the script
// is run from the web root (e.g., public_html). The .env file is expected one level above.
if (!defined('PROJECT_ROOT')) {
    // getcwd() returns the directory from which the script is run.
    // We assume this is the web root, and the project root is its parent.
    define('PROJECT_ROOT', dirname(getcwd()));
}

// Load environment variables from the .env file at the resolved project root.
$dotenv_path = PROJECT_ROOT . '/.env';
if (file_exists($dotenv_path)) {
    load_env($dotenv_path);
} else {
    // This provides a clear error if the .env file is missing from the expected location.
    die("CRITICAL ERROR: .env file not found at the expected project root: {$dotenv_path}. Please ensure the file exists and that you are running this script from your web root directory (e.g., public_html).");
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