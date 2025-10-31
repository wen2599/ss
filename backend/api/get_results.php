<?php
// backend/api/get_results.php

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

    // CORRECTED: Use CONCAT to combine 'winning_numbers' and 'special_number' into a single 'numbers' field
    // that the frontend expects. This avoids any frontend code changes.
    $results = [];
    $sql = "SELECT id, issue_number, draw_date, CONCAT(winning_numbers, '+', special_number) AS numbers, created_at FROM lottery_results ORDER BY issue_number DESC LIMIT 100";
    
    $result = $conn->query($sql);

    // Check if the query failed
    if ($result === false) {
        // Throwing an exception will be caught by the catch block
        throw new Exception("Database query failed: " . $conn->error);
    }

    // Fetch all results into an array
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }

    // Return a successful response
    echo json_encode(['success' => true, 'data' => $results]);

} catch (Exception $e) {
    // Log the actual error to the server's error log for future debugging
    error_log("API Error in get_results.php: " . $e->getMessage());

    // Send a generic 500 error response to the client
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);

} finally {
    // Ensure the database connection is closed, regardless of success or failure
    if ($conn) {
        $conn->close();
    }
}
