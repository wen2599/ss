<?php
// Action: Get bills for the logged-in user

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in to view bills.']);
    exit();
}
$user_id = $_SESSION['user_id'];

try {
    // The $pdo variable is inherited from index.php
    $sql = "SELECT id, raw_content, total_cost, status, created_at, settlement_details FROM bills WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);

    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sanitize data to prevent JSON encoding errors from invalid characters.
    // This ensures that even if the raw email content has encoding issues, the app won't crash.
    foreach ($bills as &$bill) {
        if (isset($bill['raw_content']) && is_string($bill['raw_content'])) {
            $bill['raw_content'] = mb_convert_encoding($bill['raw_content'], 'UTF-8', 'UTF-8');
        }
        if (isset($bill['settlement_details']) && is_string($bill['settlement_details'])) {
            $bill['settlement_details'] = mb_convert_encoding($bill['settlement_details'], 'UTF-8', 'UTF-8');
        }
    }
    unset($bill); // Unset the reference to the last element

    http_response_code(200);
    echo json_encode(['success' => true, 'bills' => $bills]);

} catch (PDOException $e) {
    error_log("Get Bills DB query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve bills.']);
}
?>
