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
        require_once __DIR__ . '/env_loader.php';

        // Explicitly call load_env() to get the array of variables
        $env_vars = load_env();
        
        // Get credentials from the returned array, ensuring correct key names
        $db_host = $env_vars['DB_HOST'] ?? null;
        $db_user = $env_vars['DB_USER'] ?? null;
        $db_password = $env_vars['DB_PASSWORD'] ?? null;
        $db_name = $env_vars['DB_NAME'] ?? null;
        $db_port = $env_vars['DB_PORT'] ?? null; // Assuming DB_PORT might be present in .env

        // Check if all credentials are loaded
        if (empty($db_host) || empty($db_user) || empty($db_password) || empty($db_name)) {
            error_log("DB Connection Error: Database credentials are not fully set.");
            return null; 
        }

        // Create a new database connection
        // The '@' suppresses the default PHP warning, allowing us to handle the error explicitly.
        if ($db_port) {
            @$conn = new mysqli($db_host, $db_user, $db_password, $db_name, $db_port);
        } else {
            @$conn = new mysqli($db_host, $db_user, $db_password, $db_name);
        }

        // Check for connection errors
        if ($conn->connect_error) {
            // Log the detailed error for the administrator
            error_log("CRITICAL DB Connection Failed: " . $conn->connect_error);
            return null;
        }

        // Set the character set to utf8mb4 for full Unicode support
        $conn->set_charset("utf8mb4");

        return $conn;
    }
}
