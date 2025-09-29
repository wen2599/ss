<?php
// Action: Get bills for the logged-in user

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    // Add detailed logging for debugging session issues.
    write_log("get_bills.php: Session check failed. Session data: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'error' => 'You must be logged in to view bills.']);
    exit();
}
$user_id = $_SESSION['user_id'];
write_log("get_bills.php: Authenticated for user_id: " . $user_id);

try {
    // The $pdo variable is inherited from index.php
    $sql = "SELECT id, raw_content, total_cost, status, created_at, settlement_details FROM bills WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);

    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add detailed logging for the database query result.
    write_log("get_bills.php: Found " . count($bills) . " bills for user_id: " . $user_id);
    if (empty($bills)) {
        write_log("get_bills.php: No bills found in the database for this user.");
    } else {
        write_log("get_bills.php: First bill content (for verification): " . print_r($bills[0], true));
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'bills' => $bills]);

} catch (PDOException $e) {
    error_log("Get Bills DB query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve bills.']);
}
?>
