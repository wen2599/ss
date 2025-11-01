<?php
// backend/api/get_results.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Standard headers
header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php';

// Include environment loader and database connection
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';

$conn = null;
try {
    $conn = get_db_connection();

    $results = [];
    // The query is public and does not need to be user-specific.
    // Fixed the query by removing the non-existent 'lottery_type' column.
    $sql = "SELECT id, issue_number, numbers, created_at FROM lottery_results ORDER BY issue_number DESC LIMIT 100";
    
    $result = $conn->query($sql);

    if ($result === false) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $results]);

} catch (Exception $e) {
    error_log("API Error in get_results.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred while fetching results.']);

} finally {
    if ($conn) {
        $conn->close();
    }
}
