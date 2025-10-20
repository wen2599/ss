<?php
// backend/get_lottery_results.php
// Fetches lottery results.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db_operations.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Fetch all lottery results, ordered by draw date descending.
        $results = fetchAll($pdo, "SELECT id, draw_date, winning_numbers FROM lottery_results ORDER BY draw_date DESC");

        http_response_code(200);
        echo json_encode(['success' => true, 'lotteryResults' => $results]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Invalid request method.']);
}
