<?php
// backend/delete_bill.php
// Deletes a bill for the logged-in user.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = verify_user_session($pdo);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'You must be logged in to delete bills.']);
        exit();
    }

    $userId = $user['id'];

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($data['billId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing bill ID.']);
        exit();
    }

    $billId = (int)$data['billId'];

    try {
        // Ensure the bill belongs to the logged-in user before deleting.
        $deletedRows = delete($pdo, 'bills', 'id = :bill_id AND user_id = :user_id', [
            ':bill_id' => $billId,
            ':user_id' => $userId
        ]);

        if ($deletedRows > 0) {
            http_response_code(200);
            echo json_encode(['message' => 'Bill deleted successfully.']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Bill not found or does not belong to the user.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
}
