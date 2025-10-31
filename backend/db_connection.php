<?php
// backend/db_connection.php

// Ensure this file is included only once
if (!function_exists('get_db_connection')) {
    
    /**
     * Creates and returns a new MySQLi database connection.
     * This function centralizes the database connection logic.
     *
     * @return mysqli|null The mysqli connection object on success, or null on failure.
     */
    function get_db_connection() {
        // Load environment variables using the robust loader
        // This file should already be included, but for safety, we include it here
        require_once __DIR__ . '/env_loader.php';

        // Explicitly call load_env() to get the array of variables
        $env_vars = load_env();

        // Get credentials from the returned array, ensuring correct key names
        $db_host = $env_vars['DB_HOST'] ?? null;
        $db_user = $env_vars['DB_USER'] ?? null;
        $db_pass = $env_vars['DB_PASSWORD'] ?? null; // Corrected variable name
        $db_name = $env_vars['DB_NAME'] ?? null;

        // Check if all credentials are loaded
        if (empty($db_host) || empty($db_user) || empty($db_pass) || empty($db_name)) {
            error_log("DB Connection Error: Database credentials (DB_HOST, DB_USER, DB_PASSWORD, DB_NAME) are not fully set or are empty in the .env file.");
            // For now, returning null indicates failure.
            return null; 
        }

        // Create a new database connection
        // The '@' suppresses the default PHP warning, allowing us to handle the error explicitly.
        @$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

        // Check for connection errors
        if ($conn->connect_error) {
            // Log the detailed error for the administrator
            error_log("Database connection failed: " . $conn->connect_error);
            return null;
        }

        // Set the character set to utf8mb4 for full Unicode support
        $conn->set_charset("utf8mb4");

        return $conn;
    }
}
