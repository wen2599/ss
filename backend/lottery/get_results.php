<?php // backend/lottery/get_results.php
require_once __DIR__ . '/../db_operations.php';
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT * FROM lottery_results ORDER BY drawing_date DESC, issue_number DESC LIMIT 10");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = array_map(function($row) {
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
            $decoded = json_decode($row[$key]);
            $row[$key] = $decoded ?: [];
        }
        return $row;
    }, $results);

    echo json_encode(['status' => 'success', 'data' => $processed]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not fetch lottery results.']);
}