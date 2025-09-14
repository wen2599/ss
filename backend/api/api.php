<?php
// backend/api/api.php

require_once 'config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT period, date, numbers, specialNumber FROM draws ORDER BY period DESC");
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // The 'numbers' column is stored as a JSON string, so we need to decode it.
    foreach ($draws as &$draw) {
        $draw['numbers'] = json_decode($draw['numbers']);
    }

    echo json_encode($draws);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
