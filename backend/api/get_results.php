<?php
// backend/api/get_results.php

// --- Force Error Display for Debugging ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>--- Starting Debug ---\n";

// Standard headers
header('Content-Type: application/json');
require_once __DIR__ . '/cors_headers.php';
echo "1. Headers sent.\n";

// Include database connection
require_once __DIR__ . '/../db_connection.php';
echo "2. db_connection.php included.\n";

$conn = null;
try {
    echo "3. Attempting database connection...\n";
    $conn = get_db_connection();
    echo "4. Database connection successful.\n";

    $results = [];
    $sql = "SELECT id, lottery_type, issue_number, numbers, created_at FROM lottery_results ORDER BY issue_number DESC LIMIT 100";
    echo "5. SQL query prepared: " . $sql . "\n";
    
    $result = $conn->query($sql);
    echo "6. SQL query executed.\n";

    if ($result === false) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    echo "7. Query successful. Fetching results...\n";
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }
    echo "8. " . count($results) . " results fetched.\n";

    echo "9. Encoding JSON response...\n";
    // Clear the buffer before sending the final JSON
    ob_clean();
    header('Content-Type: application/json'); // Re-set header after clearing buffer
    echo json_encode(['success' => true, 'data' => $results]);
    exit;

} catch (Exception $e) {
    // Clear buffer to ensure only the error is shown
    ob_clean();
    header('Content-Type: application/json'); // Re-set header
    error_log("API Error in get_results.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal server error occurred.',
        'error' => $e->getMessage() // Show error for debugging
    ]);
    exit;

} finally {
    if ($conn) {
        $conn->close();
    }
}
// This part is added to catch fatal errors that don't trigger the catch block
echo "\n--- End of Script (if you see this, json_encode failed or another silent error occurred) ---\n</pre>";
