<?php
// Action: Get bills for the logged-in user

write_log("get_bills.php: Script started.");

if (!isset($_SESSION['user_id'])) {
    write_log("get_bills.php: CRITICAL - No user_id in session. Aborting.");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in to view bills.']);
    exit();
}
$user_id = $_SESSION['user_id'];
write_log("get_bills.php: Authenticated user_id: " . $user_id);

try {
    write_log("get_bills.php: Preparing SQL query...");
    // The $pdo variable is inherited from index.php
    $sql = "SELECT id, total_cost, status, created_at FROM bills WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);

    write_log("get_bills.php: Executing query for user_id: " . $user_id);
    $stmt->execute([':user_id' => $user_id]);
    write_log("get_bills.php: Query executed successfully.");

    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode(['success' => true, 'bills' => $bills]);

} catch (PDOException $e) {
    error_log("Get Bills DB query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to retrieve bills.']);
}
?>
