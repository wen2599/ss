<?php
// Action: Settles a bill against the latest lottery results.

require_once __DIR__ . '/../lib/BetCalculator.php';

// 1. Authenticate user
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}
$user_id = $_SESSION['user_id'];

// 2. Get bill_id from POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Only POST method is allowed.']);
    exit();
}
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['bill_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bill ID is required.']);
    exit();
}
$bill_id = $data['bill_id'];

try {
    $pdo->beginTransaction();

    // 3. Fetch the bill to be settled, ensuring user ownership
    $stmt = $pdo->prepare("SELECT settlement_details FROM bills WHERE id = :bill_id AND user_id = :user_id FOR UPDATE");
    $stmt->execute([':bill_id' => $bill_id, ':user_id' => $user_id]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill || empty($bill['settlement_details'])) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bill not found, has no details, or you do not have permission.']);
        exit();
    }
    $bill_details = json_decode($bill['settlement_details'], true);

    // 4. Fetch the latest lottery results for Hong Kong and New Macau
    $lottery_types = ['香港', '新澳门'];
    $lottery_results_map = [];
    foreach ($lottery_types as $type) {
        $sql = "SELECT numbers FROM lottery_results WHERE lottery_name LIKE :lottery_name ORDER BY parsed_at DESC, id DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lottery_name' => '%' . $type . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lottery_results_map[$type] = explode(',', $result['numbers']);
        }
    }

    if (empty($lottery_results_map)) {
         $pdo->rollBack();
         http_response_code(400);
         echo json_encode(['success' => false, 'error' => 'No recent lottery results found in the database to settle against.']);
         exit();
    }

    // 5. Perform settlement calculation
    $settled_details = BetCalculator::settle($bill_details, $lottery_results_map);

    // 6. Update the bill with the settled details
    $update_stmt = $pdo->prepare("UPDATE bills SET settlement_details = :details, status = 'settled' WHERE id = :id");
    $update_stmt->execute([
        ':details' => json_encode($settled_details, JSON_UNESCAPED_UNICODE),
        ':id' => $bill_id
    ]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Bill settled successfully.']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Settle Bill DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A server error occurred during settlement.']);
}
?>