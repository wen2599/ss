<?php
// Action: Update the settlement result for a single slip and recalculate the bill's summary.

// Ensure the user is authenticated.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed.']);
    exit();
}

// The main logic is wrapped in a try-catch block to handle all potential errors gracefully.
try {
    // Use JSON_THROW_ON_ERROR to turn JSON errors into catchable exceptions.
    $data = json_decode(file_get_contents("php://input"), true, 512, JSON_THROW_ON_ERROR);
    $user_id = $_SESSION['user_id'];

    // Validate input
    if (!isset($data['bill_id']) || !isset($data['slip_index']) || !isset($data['settlement_result'])) {
        http_response_code(400);
        throw new InvalidArgumentException('Invalid input. Missing bill_id, slip_index, or settlement_result.');
    }
    if (!is_array($data['settlement_result'])) {
        http_response_code(400);
        throw new InvalidArgumentException('settlement_result must be a valid JSON object.');
    }

    $bill_id = $data['bill_id'];
    $slip_index = $data['slip_index'];
    $new_settlement_result = $data['settlement_result'];

    $pdo->beginTransaction();

    // 1. Fetch and lock the bill to ensure data consistency
    $stmt = $pdo->prepare("SELECT settlement_details FROM bills WHERE id = :bill_id AND user_id = :user_id FOR UPDATE");
    $stmt->execute([':bill_id' => $bill_id, ':user_id' => $user_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        $pdo->rollBack();
        http_response_code(404);
        throw new RuntimeException('Bill not found or you do not have permission to edit it.');
    }

    // 2. Decode the existing details with exception on error
    $details = json_decode($bill['settlement_details'], true, 512, JSON_THROW_ON_ERROR);

    // 3. Validate the slip index
    if (!isset($details['slips'][$slip_index])) {
        $pdo->rollBack();
        http_response_code(400);
        throw new RuntimeException('Invalid slip index.');
    }

    // 4. Replace the old result with the new one
    $details['slips'][$slip_index]['result'] = $new_settlement_result;

    // 5. Recalculate ALL summary fields to ensure data consistency after the edit
    $new_total_cost = 0;
    $new_total_winnings = 0;
    foreach ($details['slips'] as $slip) {
        $new_total_cost += $slip['result']['summary']['total_cost'] ?? 0;
        $new_total_winnings += $slip['result']['summary']['winnings'] ?? 0;
    }
    $details['summary']['total_cost'] = $new_total_cost;
    $details['summary']['total_winnings'] = $new_total_winnings;
    $details['summary']['net_result'] = $new_total_winnings - $new_total_cost;

    // 6. Encode the updated details and save back to the database
    $new_details_json = json_encode($details, JSON_UNESCAPED_UNICODE);
    $update_stmt = $pdo->prepare("UPDATE bills SET settlement_details = :details, total_cost = :cost WHERE id = :id");
    $update_stmt->execute([
        ':details' => $new_details_json,
        ':cost' => $new_total_cost,
        ':id' => $bill_id
    ]);

    // 7. Commit the transaction
    $pdo->commit();

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Settlement updated and recalculated successfully.']);

} catch (JsonException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Update settlement JSON error: " . $e->getMessage());
    http_response_code(400); // Bad Request for malformed JSON
    echo json_encode(['success' => false, 'error' => 'Invalid JSON format: ' . $e->getMessage()]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Update settlement DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A database error occurred during the update.']);
} catch (Throwable $t) {
    // Generic catch-all for any other errors (e.g., TypeError, InvalidArgumentException)
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Critical error in update_settlement.php: " . $t->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A critical server error occurred: ' . $t->getMessage()]);
}
?>