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

if (!isset($data['bill_id']) || !isset($data['slip_index']) || !isset($data['settlement_text'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input. Missing bill_id, slip_index, or settlement_text.']);
    exit();
}

$bill_id = $data['bill_id'];
$slip_index = $data['slip_index'];
$settlement_text = $data['settlement_text'];
$user_id = $_SESSION['user_id'];

try {
    // The $pdo variable is inherited from index.php

    // First, verify the user owns this bill and get the current details
    $stmt = $pdo->prepare("SELECT settlement_details FROM bills WHERE id = :bill_id AND user_id = :user_id");
    $stmt->execute([':bill_id' => $bill_id, ':user_id' => $user_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bill not found or you do not have permission to edit it.']);
        exit();
    }

    $settlement_details = json_decode($bill['settlement_details'], true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($settlement_details)) {
         http_response_code(500);
         echo json_encode(['success' => false, 'error' => 'Could not parse existing settlement details.']);
         exit();
    }

    // Check if the slip index is valid within the 'slips' array
    if (!is_array($settlement_details) || !isset($settlement_details['slips']) || !isset($settlement_details['slips'][$slip_index])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid slip index or data structure.']);
        exit();
    }

    // Update the settlement text for the specific slip within its 'result' object
    if (!isset($settlement_details['slips'][$slip_index]['result'])) {
        // If for some reason 'result' doesn't exist, create it.
        $settlement_details['slips'][$slip_index]['result'] = [];
    }
    $settlement_details['slips'][$slip_index]['result']['settlement'] = $settlement_text;

    // Encode the updated array back to JSON
    $new_settlement_details_json = json_encode($settlement_details, JSON_UNESCAPED_UNICODE);

    // Save the updated JSON back to the database
    $update_stmt = $pdo->prepare("UPDATE bills SET settlement_details = :settlement_details WHERE id = :bill_id");
    $update_stmt->execute([
        ':settlement_details' => $new_settlement_details_json,
        ':bill_id' => $bill_id
    ]);

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Settlement updated successfully.']);

} catch (PDOException $e) {
    error_log("Update settlement DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A server error occurred while updating the settlement.']);
}
?>