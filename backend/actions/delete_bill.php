<?php
// Action: Delete a bill for the logged-in user

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'You must be logged in to delete bills.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['bill_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bill ID is required.']);
    exit();
}

$bill_id = $data['bill_id'];

try {
    // The $pdo variable is inherited from index.php
    $sql = "DELETE FROM bills WHERE id = :bill_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':bill_id' => $bill_id, ':user_id' => $user_id]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Bill deleted successfully.']);
    } else {
        // This can happen if the bill does not exist or does not belong to the user
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Bill not found or you do not have permission to delete it.']);
    }

} catch (PDOException $e) {
    error_log("Delete Bill DB query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete the bill.']);
}
?>
