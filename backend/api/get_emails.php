<?php
// backend/api/get_emails.php

// Set rigorous error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Standard headers
header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php'; // Handle Cross-Origin Resource Sharing

// Include the database connection function
require_once __DIR__ . '/../db_connection.php';

$conn = null; // Initialize connection variable
try {
    // Get the database connection using the centralized function
    $conn = get_db_connection();

    $emails = [];
    // Fetch the latest 100 emails, ordered by received time
    $sql = "SELECT id, message_id, from_address, subject, received_at FROM emails ORDER BY received_at DESC LIMIT 100";
    
    $result = $conn->query($sql);

    // Check if the query failed
    if ($result === false) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    // Fetch all results into an array
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $emails[] = $row;
        }
    }

    // Return a successful response
    echo json_encode(['success' => true, 'data' => $emails]);

} catch (Exception $e) {
    // Log the actual error to the server's error log for future debugging
    error_log("API Error in get_emails.php: " . $e->getMessage());

    // Send a generic 500 error response to the client
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);

} finally {
    // Ensure the database connection is closed, regardless of success or failure
    if ($conn) {
        $conn->close();
    }
}
