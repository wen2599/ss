<?php
// backend/api/get_results.php

header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php';

// Load environment variables and establish the database connection.
// These files now work together reliably.
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connector.php';

$pdo = null;
try {
    $pdo = get_db_connection();

    if (!$pdo) {
        // This failure condition will now only be met if file loading fails or config is missing.
        throw new Exception("Failed to obtain a valid database connection.");
    }

    $sql = "SELECT id, lottery_type, issue_number, numbers, created_at FROM lottery_results ORDER BY issue_number DESC LIMIT 100";
    
    $stmt = $pdo->query($sql);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $results]);

} catch (Exception $e) {
    // Log the error for the admin, but don't show it to the user.
    error_log("API Error in get_results.php: " . $e->getMessage());

    // Send a generic, secure error message to the client.
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred while fetching results.']);

} finally {
    // Ensure the connection is closed.
    $pdo = null;
}
