<?php
// backend/db_connector.php

/**
 * Establishes a database connection using PDO and returns the connection object.
 * It reads configuration by calling the load_env() function.
 *
 * @return PDO|null A PDO connection object on success, or null on failure.
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // env_loader.php should have been included before this function is called.
    // We rely on the more reliable array-based approach instead of getenv().
    if (!function_exists('load_env')) {
        error_log('CRITICAL: load_env() function not found. env_loader.php must be included first.');
        return null;
    }
    
    // Call the function to get the array of environment variables.
    $env = load_env();

    $host    = $env['DB_HOST'] ?? null;
    $db_name = $env['DB_NAME'] ?? null;
    $user    = $env['DB_USER'] ?? null;
    // CORRECTED: Use DB_PASSWORD to match the .env file.
    $pass    = $env['DB_PASSWORD'] ?? null;
    $charset = 'utf8mb4';

    // Check if the essential variables were loaded from the array.
    if (!$host || !$db_name || !$user) {
        error_log('Database configuration variables (DB_HOST, DB_NAME, DB_USER) could not be loaded from the environment array.');
        return null;
    }

    $dsn = "mysql:host={$host};dbname={$db_name};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed via PDO: ' . $e->getMessage());
        return null;
    }
}
