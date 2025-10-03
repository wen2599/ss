<?php
/**
 * Action: get_bills
 *
 * This script retrieves all bills associated with the currently authenticated user.
 * The bills are returned in descending order of creation time.
 *
 * HTTP Method: GET
 *
 * Response:
 * - On success: { "success": true, "bills": [ { "id": int, "raw_content": string, ... } ] }
 * - On error (e.g., not logged in): { "success": false, "error": "Error message." }
 */

// The main router (index.php) handles initialization and session start.
// Global variables $pdo and $log are available.

// 1. Authorization: Check if the user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    $log->warning("Unauthorized attempt to get bills.", ['ip_address' => $_SERVER['REMOTE_ADDR']]);
    echo json_encode(['success' => false, 'error' => 'Authentication required to view bills.']);
    exit();
}

$userId = $_SESSION['user_id'];

// 2. Database Operation
try {
    $sql = "SELECT id, raw_content, total_cost, status, created_at, settlement_details
            FROM bills
            WHERE user_id = :user_id
            ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);

    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    $log->info("Successfully retrieved bills for user.", ['user_id' => $userId, 'bill_count' => count($bills)]);
    echo json_encode(['success' => true, 'bills' => $bills]);

} catch (PDOException $e) {
    // The global exception handler in init.php will catch this, but we log here for context.
    $log->error("Database error while retrieving bills.", [
        'user_id' => $userId,
        'error' => $e->getMessage()
    ]);
    // Re-throw to let the global handler manage the response
    throw $e;
}
?>