<?php
// backend/lib/helpers.php

/**
 * Sends a JSON response with a specified HTTP status code.
 *
 * @param mixed $data The data to encode as JSON.
 * @param int $status_code The HTTP status code to set.
 */
function send_json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Establishes a database connection and returns the connection object.
 *
 * @return mysqli|null The mysqli connection object on success, or null on failure.
 */
function get_db_connection() {
    // The @ suppresses the default PHP warning, allowing for custom error handling.
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // In a real application, you would log this error more robustly.
        // For now, we'll return null to indicate failure.
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }

    return $conn;
}
?>