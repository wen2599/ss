<?php
// backend/bootstrap.php

// This is the central bootstrap file for the entire PHP backend.
// It handles environment loading and database connection.

// 1. Load the dependency-free environment variable loader
// This replaces the need for Composer and the phpdotenv library.
require_once __DIR__ . '/load_env.php';

// The load_env.php script automatically loads variables from the root .env file
// into getenv(), $_ENV, and $_SERVER.

// 2. Establish Database Connection

// Set a global variable for the database connection
$db_connection = null;

try {
    // Retrieve database credentials securely from the environment.
    // Using getenv() is a standard way to access these.
    $db_host = getenv('DB_HOST');
    $db_user = getenv('DB_USER');
    $db_pass = getenv('DB_PASS');
    $db_name = getenv('DB_NAME');

    // Check if all required environment variables are set
    if (!$db_host || !$db_user || !$db_name) {
        throw new Exception("Database credentials (DB_HOST, DB_USER, DB_NAME) are not fully set in the .env file.");
    }

    // Use mysqli for the database connection
    // The @ symbol suppresses warnings, allowing our custom error handling to take over.
    $db_connection = new mysqli($db_host, $db_user, $db_pass, $db_name);

    // Check for a connection error
    if ($db_connection->connect_error) {
        // Throw an exception to be caught by the block below.
        throw new Exception($db_connection->connect_error);
    }

    // Set the character set to utf8mb4 for full Unicode support.
    $db_connection->set_charset("utf8mb4");

} catch (Exception $e) {
    // In case of any error (missing variables or connection failure),
    // send a generic 500 server error and log the detailed message.
    http_response_code(500);
    // Log the actual error to the server's error log for debugging.
    error_log("Database connection failed: " . $e->getMessage());
    // Display a generic error to the client for security.
    echo "[FATAL] A critical error occurred on the server. Please check the logs.";
    exit;
}

// The $db_connection variable is now globally available for any script that includes this bootstrap file.
