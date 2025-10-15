<?php
// backend/delete_bill.php

// This script is now routed through index.php, which handles the header.

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in to perform this action.']);
    exit;
}

// Check if the request method is DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$pdo = get_db_connection();
$userId = $_SESSION['user_id'];
$emailId = $_GET['id'] ?? null;

if (!$emailId) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Bill ID is required.']);
    exit;
}

try {
    // Prepare and execute the delete query
    // IMPORTANT: The WHERE clause includes user_id to ensure a user can only delete their own bills.
    $stmt = $pdo->prepare("DELETE FROM emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$emailId, $userId]);

    // Check if any row was actually deleted
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => '账单已成功删除。']);
    } else {
        // This means either the bill didn't exist or it didn't belong to the user
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => '未找到该账单或无权删除。']);
    }
} catch (PDOException $e) {
    error_log("Error deleting bill: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while deleting the bill.']);
}
