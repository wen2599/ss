<?php
// backend/api/get_results.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php';

require_once __DIR__ . '/../db_connection.php';

$conn = null;
try {
    $conn = get_db_connection();
    
    if (!$conn) {
        throw new Exception("Database connection failed.");
    }

    $results = [];
    // SECOND GUESS: Changed 'winning_numbers' to 'drawn_numbers'.
    $sql = "SELECT id, issue_number, draw_date, drawn_numbers, created_at FROM lottery_results ORDER BY issue_number DESC LIMIT 100";
    
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
    // -- TEMPORARY DEBUGGING REMAINS ACTIVE --
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An internal server error occurred.', 
        'error_details' => $e->getMessage() 
    ]);
    // -- END TEMPORARY DEBUGGING --

} finally {
    if ($conn) {
        $conn->close();
    }
}
