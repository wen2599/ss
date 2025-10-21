<?php
// backend/get_bills.php
// Fetches bills for the logged-in user.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = verify_user_session($pdo);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'You must be logged in to view bills.']);
        exit();
    }

    $userId = $user['id'];

    try {
        $bills = fetchAll($pdo, "SELECT id, subject, amount, due_date, status, is_lottery, lottery_numbers, received_at FROM bills WHERE user_id = :user_id ORDER BY received_at DESC", [
            ':user_id' => $userId
        ]);

        http_response_code(200);
        echo json_encode(['success' => true, 'bills' => $bills]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
}
