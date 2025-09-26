<?php
file_put_contents(__DIR__ . '/debug_log.txt', "--- auto_settle_slip.php started ---\n", FILE_APPEND);

require_once __DIR__ . '/../lib/SettlementCalculator.php';

// Action: Automatically settles a single slip within a bill and updates it.

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

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['bill_id']) || !isset($data['slip_index'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input. Missing bill_id or slip_index.']);
    exit();
}

$bill_id = $data['bill_id'];
$slip_index = $data['slip_index'];
$user_id = $_SESSION['user_id'];

try {
    // The $pdo variable is inherited from index.php

    // 1. Verify the user owns this bill and get the current details
    $stmt = $pdo->prepare("SELECT settlement_details FROM bills WHERE id = :bill_id AND user_id = :user_id");
    $stmt->execute([':bill_id' => $bill_id, ':user_id' => $user_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bill not found or you do not have permission to edit it.']);
        exit();
    }

    $settlement_details = json_decode($bill['settlement_details'], true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($settlement_details) || !isset($settlement_details[$slip_index])) {
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'Could not parse existing settlement details or invalid slip index.']);
         exit();
    }

    $raw_slip_text = $settlement_details[$slip_index]['raw'];

    // 2. Get the latest lottery result
    $lottery_stmt = $pdo->query("SELECT numbers, issue_number FROM lottery_results ORDER BY parsed_at DESC LIMIT 1");
    $latest_result_raw = $lottery_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$latest_result_raw) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No lottery results found in the database.']);
        exit();
    }

    // The numbers string is "01,02,03,04,05,06,07", with the last being special.
    $numbers_array = explode(',', $latest_result_raw['numbers']);
    $special_number = array_pop($numbers_array);
    $lottery_result = [
        'numbers' => $numbers_array,
        'special' => $special_number,
        'issue_number' => $latest_result_raw['issue_number']
    ];

    // 3. Call the SettlementCalculator
    $settlement_text = SettlementCalculator::settle($raw_slip_text, $lottery_result);
    $settlement_text_with_issue = "根据第 {$lottery_result['issue_number']} 期开奖结果：\n" . $settlement_text;

    // 4. Update the settlement text for the specific slip
    $settlement_details[$slip_index]['settlement'] = $settlement_text_with_issue;
    $new_settlement_details_json = json_encode($settlement_details, JSON_UNESCAPED_UNICODE);

    // 5. Save the updated JSON back to the database
    $update_stmt = $pdo->prepare("UPDATE bills SET settlement_details = :settlement_details WHERE id = :bill_id");
    $update_stmt->execute([
        ':settlement_details' => $new_settlement_details_json,
        ':bill_id' => $bill_id
    ]);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Auto-settlement successful.']);

} catch (PDOException $e) {
    error_log("Auto-settle DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A server error occurred during auto-settlement.']);
}
?>