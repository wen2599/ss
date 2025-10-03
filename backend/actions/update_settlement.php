<?php
/**
 * Action: update_settlement
 *
 * This script updates the settlement result for a single slip within a bill and
 * recalculates the bill's total cost. The entire operation is performed within a
 * database transaction to ensure data integrity.
 *
 * HTTP Method: POST
 *
 * Request Body (JSON):
 * - "bill_id" (integer): The ID of the bill to update.
 * - "slip_index" (integer): The array index of the slip to update within the settlement details.
 * - "settlement_result" (object): The new JSON object representing the settlement result for that slip.
 *
 * Response:
 * - On success: { "success": true, "message": "Settlement updated successfully." }
 * - On error: { "success": false, "error": "Error message." }
 */

// The main router (index.php) handles initialization.
// Global variables $pdo and $log are available.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $log->warning("Method not allowed for update_settlement.", ['method' => $_SERVER['REQUEST_METHOD']]);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

// 1. Authorization
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    $log->warning("Unauthorized attempt to update settlement.", ['ip_address' => $_SERVER['REMOTE_ADDR']]);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

// 2. Input Validation
if (
    !isset($data['bill_id']) || !filter_var($data['bill_id'], FILTER_VALIDATE_INT) ||
    !isset($data['slip_index']) || !is_int($data['slip_index']) ||
    !isset($data['settlement_result']) || !is_array($data['settlement_result'])
) {
    http_response_code(400); // Bad Request
    $log->warning("Bad request to update_settlement: Missing or invalid fields.", ['user_id' => $userId, 'data' => $data]);
    echo json_encode(['success' => false, 'error' => 'Invalid input. bill_id (int), slip_index (int), and settlement_result (object) are required.']);
    exit();
}

$billId = (int) $data['bill_id'];
$slipIndex = (int) $data['slip_index'];
$newSettlementResult = $data['settlement_result'];

// 3. Database Transaction
try {
    $pdo->beginTransaction();

    // Step 3.1: Fetch and lock the bill to ensure data consistency.
    $stmt = $pdo->prepare("SELECT settlement_details FROM bills WHERE id = :bill_id AND user_id = :user_id FOR UPDATE");
    $stmt->execute([':bill_id' => $billId, ':user_id' => $userId]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bill not found or you do not have permission to edit it.']);
        $pdo->rollBack();
        exit();
    }

    // Step 3.2: Decode the existing settlement details.
    $details = json_decode($bill['settlement_details'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($details) || !isset($details['slips'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not parse existing settlement details. The bill data may be corrupt.']);
        $pdo->rollBack();
        exit();
    }

    // Step 3.3: Validate the slip index.
    if (!isset($details['slips'][$slipIndex])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid slip index provided.']);
        $pdo->rollBack();
        exit();
    }

    // Step 3.4: Replace the old result with the new one.
    $details['slips'][$slipIndex]['result'] = $newSettlementResult;

    // Step 3.5: Recalculate the bill's total cost from all slips.
    $newTotalCost = 0;
    foreach ($details['slips'] as $slip) {
        if (isset($slip['result']['summary']['total_cost'])) {
            $newTotalCost += $slip['result']['summary']['total_cost'];
        }
    }
    $details['summary']['total_cost'] = $newTotalCost;

    // Step 3.6: Encode the updated details and save back to the database.
    $newDetailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
    $updateStmt = $pdo->prepare("UPDATE bills SET settlement_details = :details, total_cost = :cost WHERE id = :id");
    $updateStmt->execute([
        ':details' => $newDetailsJson,
        ':cost' => $newTotalCost,
        ':id' => $billId
    ]);

    // Step 3.7: Commit the transaction.
    $pdo->commit();

    http_response_code(200);
    $log->info("Settlement updated successfully.", ['user_id' => $userId, 'bill_id' => $billId]);
    echo json_encode(['success' => true, 'message' => 'Settlement updated and total cost recalculated successfully.']);

} catch (Exception $e) {
    // Rollback the transaction on any error.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // The global exception handler in init.php will catch this.
    $log->error("Error during settlement update transaction.", [
        'user_id' => $userId,
        'bill_id' => $billId,
        'error' => $e->getMessage()
    ]);
    throw $e;
}
?>