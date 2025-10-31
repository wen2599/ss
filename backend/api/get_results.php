<?php
// backend/api/get_results.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php';

// CORRECTED: The env_loader will now correctly populate the environment.
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connector.php';

$pdo = null;
try {
    // Establish connection using the centralized PDO function
    $pdo = get_db_connection();

    if (!$pdo) {
        // This will be caught by the main catch block
        throw new Exception("Database connection failed.");
    }

    // The SQL query to fetch lottery results.
    $sql = "SELECT id, lottery_type, issue_number, numbers, created_at FROM lottery_results ORDER BY issue_number DESC LIMIT 100";
    
    $stmt = $pdo->query($sql);

    // Fetch all results using PDO's recommended method
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return a successful response
    echo json_encode(['success' => true, 'data' => $results]);

} catch (Exception $e) {
    // Log the detailed error for the administrator for future debugging.
    error_log("API Error in get_results.php: " . $e->getMessage());

    // Send a generic, user-friendly error response to the client.
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred while fetching results.']);

} finally {
    // Ensure the database connection is closed.
    $pdo = null;
}
