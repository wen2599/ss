<?php
/**
 * Action: delete_bill
 *
 * This script handles the deletion of a specific bill for the currently authenticated user.
 * It ensures that a user can only delete their own bills.
 *
 * HTTP Method: POST
 *
 * Request Body (JSON):
 * - "bill_id" (integer): The ID of the bill to be deleted.
 *
 * Response:
 * - On success: { "success": true, "message": "Bill deleted successfully." }
 * - On error (e.g., not logged in, missing ID, permission denied): { "success": false, "error": "Error message." }
 */

// The main router (index.php) handles initialization and session start.
// Global variables $pdo and $log are available.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $log->warning("Method not allowed for delete_bill.", ['method' => $_SERVER['REQUEST_METHOD']]);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

// 1. Authorization: Check if the user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    $log->warning("Unauthorized attempt to delete a bill.", ['ip_address' => $_SERVER['REMOTE_ADDR']]);
    echo json_encode(['success' => false, 'error' => 'Authentication required to delete bills.']);
    exit();
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

// 2. Validation: Check if bill_id is provided and is a valid integer.
if (!isset($data['bill_id']) || !filter_var($data['bill_id'], FILTER_VALIDATE_INT)) {
    http_response_code(400); // Bad Request
    $log->warning("Bad request to delete_bill: bill_id is missing or invalid.", ['user_id' => $userId, 'data' => $data]);
    echo json_encode(['success' => false, 'error' => 'A valid bill ID is required.']);
    exit();
}

$billId = (int) $data['bill_id'];

// 3. Database Operation
try {
    $sql = "DELETE FROM bills WHERE id = :bill_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':bill_id' => $billId, ':user_id' => $userId]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        $log->info("Bill deleted successfully.", ['user_id' => $userId, 'bill_id' => $billId]);
        echo json_encode(['success' => true, 'message' => 'Bill deleted successfully.']);
    } else {
        // This occurs if the bill does not exist or does not belong to the user.
        // For security, we don't differentiate between "not found" and "permission denied".
        http_response_code(404); // Not Found
        $log->warning("Failed to delete bill: Not found or permission denied.", ['user_id' => $userId, 'bill_id' => $billId]);
        echo json_encode(['success' => false, 'error' => 'Bill not found or you do not have permission to delete it.']);
    }

} catch (PDOException $e) {
    // The global exception handler in init.php will catch this, but we can log it here for more context.
    $log->error("Database error while deleting bill.", [
        'user_id' => $userId,
        'bill_id' => $billId,
        'error' => $e->getMessage()
    ]);
    // Re-throw to let the global handler manage the response
    throw $e;
}
?>