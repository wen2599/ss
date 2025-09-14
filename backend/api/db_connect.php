<?php
// backend/api/db_connect.php
require_once 'config.php';

/**
 * Establishes a connection to the MySQL database using MySQLi.
 *
 * @return mysqli|null Returns the mysqli connection object on success, or null on failure.
 */
function db_connect() {
    // Create connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        // Log the error, but don't expose details in a production environment.
        // Make sure your PHP error logging is configured correctly on the server.
        error_log("MySQLi Connection Failed: " . $conn->connect_error);
        return null;
    }

    // Set character set to utf8mb4 for full Unicode support.
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error loading character set utf8mb4: " . $conn->error);
        // We might still be able to work, but it's a warning sign.
    }

    return $conn;
}
?>
