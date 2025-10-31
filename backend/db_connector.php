<?php
// backend/db_connector.php

/**
 * Establishes a database connection using PDO and returns the connection object.
 * It reads configuration from environment variables.
 *
 * @return PDO|null A PDO connection object on success, or null on failure.
 */
function get_db_connection() {
    // This static variable will hold the connection object
    static $pdo = null;

    // If the connection is already established, return it
    if ($pdo !== null) {
        return $pdo;
    }

    // Load environment variables if they aren't already
    // The env_loader.php should be included before calling this function
    $host = getenv('DB_HOST');
    $db_name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $charset = 'utf8mb4';

    // Check if the essential variables are set
    if (!$host || !$db_name || !$user) {
        error_log('Database configuration environment variables are not fully set.');
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
        // Log the error securely without exposing details to the user
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
}
