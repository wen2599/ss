<?php
// backend/get_bills.php
// Fetches bills for the logged-in user.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/check_session.php'; // To ensure user is logged in

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Re-use the session checking logic. If not logged in, it will exit.
    // For a cleaner approach, you might refactor check_session into a function.
    ob_start(); // Start output buffering
    require __DIR__ . '/check_session.php';
    $session_check_output = json_decode(ob_get_clean(), true); // Get and decode output

    if (!isset($session_check_output['isLoggedIn']) || !$session_check_output['isLoggedIn']) {
        // check_session.php already sent appropriate headers and exit, 
        // but for robustness, ensure no further execution if not logged in.
        // If you refactor check_session to return a value, this would be simpler.
        exit(); 
    }

    $userId = $session_check_output['user']['id'];

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
