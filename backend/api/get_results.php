<?php
// backend/api/get_results.php
// Version 2.0: Refactored for centralized routing.

// Ensure $conn is available from the router context.
if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Router configuration error: Database connection not available.']);
    exit;
}

try {
    $results = [];
    // The query is public and does not need to be user-specific.
    $sql = "SELECT id, issue_number, numbers, created_at FROM lottery_results ORDER BY issue_number DESC LIMIT 100";
    
    $result = $conn->query($sql);

    if ($result === false) {
        // The router will handle closing the connection, but we can log the specific error.
        error_log("API Error in get_results.php: Database query failed: " . $conn->error);
        throw new Exception("Database query failed.");
    }

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $results]);

} catch (Exception $e) {
    // A generic error is sent to the client, details are logged.
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred while fetching results.']);
}

// The connection will be closed by api_router.php
