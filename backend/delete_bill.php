<?php
require_once __DIR__ . '/bootstrap.php';

write_log("------ delete_bill.php Entry Point ------");

// --- Authentication Check ---
if (!isset($_SESSION['user_id'])) {
    json_response('error', 'Unauthorized: User not logged in.', 401);
}

// Check if the request method is DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    json_response('error', 'Invalid request method. Only DELETE is allowed.', 405);
}

$userId = $_SESSION['user_id'];

// Expect bill ID from query parameter
$billId = $_GET['id'] ?? null;

if (!$billId) {
    json_response('error', 'Bill ID is required.', 400);
}

try {
    $pdo = get_db_connection();
    // Prepare and execute the delete query from the 'bills' table
    // IMPORTANT: The WHERE clause includes user_id to ensure a user can only delete their own bills.
    $stmt = $pdo->prepare("DELETE FROM bills WHERE id = ? AND user_id = ?");
    $stmt->execute([$billId, $userId]);

    // Check if any row was actually deleted
    if ($stmt->rowCount() > 0) {
        write_log("Bill ID {$billId} deleted successfully by user {$userId}.");
        json_response('success', '账单已成功删除。');
    } else {
        // This means either the bill didn't exist or it didn't belong to the user
        write_log("Failed to delete bill ID {$billId} for user {$userId}: Not found or unauthorized.");
        json_response('error', '未找到该账单或无权删除。', 404);
    }
} catch (PDOException $e) {
    write_log("Database error in delete_bill.php: " . $e->getMessage());
    json_response('error', 'An error occurred while deleting the bill.', 500);
}

write_log("------ delete_bill.php Exit Point ------");
