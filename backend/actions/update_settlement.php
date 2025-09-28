<?php
// Action: Update the settlement text for a single slip within a bill.

// Ensure the user is authenticated. The session is started in index.php.
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

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['bill_id']) || !isset($data['slip_index']) || !isset($data['settlement_result'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input. Missing bill_id, slip_index, or settlement_result.']);
    exit();
}

$bill_id = $data['bill_id'];
$slip_index = $data['slip_index'];
$new_settlement_result = $data['settlement_result'];
$user_id = $_SESSION['user_id'];

if (!is_array($new_settlement_result)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'settlement_result must be a valid JSON object.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Verify user owns the bill and lock the row for update
    $stmt = $pdo->prepare("SELECT settlement_details FROM bills WHERE id = :bill_id AND user_id = :user_id FOR UPDATE");
    $stmt->execute([':bill_id' => $bill_id, ':user_id' => $user_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bill not found or you do not have permission to edit it.']);
        $pdo->rollBack();
        exit();
    }

    $details = json_decode($bill['settlement_details'], true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($details) || !isset($details['slips'])) {
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'Could not parse existing settlement details.']);
         $pdo->rollBack();
         exit();
    }

    // 2. Check if the slip index is valid
    if (!isset($details['slips'][$slip_index])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid slip index.']);
        $pdo->rollBack();
        exit();
    }

    // 3. Replace the old result with the new one
    $details['slips'][$slip_index]['result'] = $new_settlement_result;

    // 4. Recalculate the bill's total cost
    $new_total_cost = 0;
    foreach ($details['slips'] as $slip) {
        $new_total_cost += $slip['result']['summary']['total_cost'] ?? 0;
    }
    $details['summary']['total_cost'] = $new_total_cost;
    $details['summary']['total_number_count'] = 0; // Recalculate if needed, omitted for now.

    // 5. Save the updated details and total_cost back to the database
    $new_details_json = json_encode($details, JSON_UNESCAPED_UNICODE);
    $update_stmt = $pdo->prepare("UPDATE bills SET settlement_details = :details, total_cost = :cost WHERE id = :id");
    $update_stmt->execute([
        ':details' => $new_details_json,
        ':cost' => $new_total_cost,
        ':id' => $bill_id
    ]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Settlement updated and total cost recalculated successfully.']);

} catch (PDOException $e) {
    error_log("Update settlement DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A server error occurred while updating the settlement.']);
}
?>