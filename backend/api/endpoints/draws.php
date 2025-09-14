<?php
// backend/api/endpoints/draws.php

require_once __DIR__ . '/../db.php';

$db = get_db();
$endpoint = $_GET['endpoint'] ?? '';

if ($endpoint === 'get_draws') {
    $stmt = $db->query("SELECT * FROM lottery_draws ORDER BY draw_date DESC");
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    Response::send_json(['success' => true, 'draws' => $draws]);
} elseif ($endpoint === 'create_draw' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $draw_number = $data['draw_number'] ?? null;
    $draw_date = $data['draw_date'] ?? null;

    if (!$draw_number || !$draw_date) {
        Response::send_json_error(400, 'Missing required fields for creating a draw.');
    }

    try {
        $stmt = $db->prepare("INSERT INTO lottery_draws (draw_number, draw_date) VALUES (?, ?)");
        $stmt->execute([$draw_number, $draw_date]);
        $new_draw_id = $db->lastInsertId();
        Response::send_json(['success' => true, 'message' => 'Draw created successfully.', 'draw_id' => $new_draw_id]);
    } catch (Exception $e) {
        Response::send_json_error(500, 'Failed to create draw.', $e->getMessage());
    }
} else {
    Response::send_json_error(404, 'Draw endpoint not found');
}
