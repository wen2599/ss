<?php
// backend/api/endpoints/bets.php

require_once __DIR__ . '/../db.php';

$db = get_db();
$endpoint = $_GET['endpoint'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    Response::send_json_error(401, 'You must be logged in to view this page.');
}

if ($endpoint === 'place_bet' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $draw_id = $data['draw_id'] ?? null;
    $bet_type = $data['bet_type'] ?? null;
    $bet_numbers = $data['bet_numbers'] ?? null;
    $bet_amount = $data['bet_amount'] ?? null;

    if (!$draw_id || !$bet_type || !$bet_numbers || !$bet_amount) {
        Response::send_json_error(400, 'Missing required fields for placing a bet.');
    }

    // Start a transaction
    $db->beginTransaction();

    try {
        // Check if user has enough points
        $stmt = $db->prepare("SELECT points FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_points = $stmt->fetchColumn();

        if ($user_points < $bet_amount) {
            Response::send_json_error(400, 'Not enough points to place this bet.');
        }

        // Deduct points from user
        $stmt = $db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $stmt->execute([$bet_amount, $user_id]);

        // Insert the bet
        $stmt = $db->prepare("INSERT INTO bets (user_id, draw_id, bet_type, bet_numbers, bet_amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $draw_id, $bet_type, json_encode($bet_numbers), $bet_amount]);

        // Commit the transaction
        $db->commit();

        Response::send_json(['success' => true, 'message' => 'Bet placed successfully.']);
    } catch (Exception $e) {
        // Rollback the transaction on error
        $db->rollBack();
        Response::send_json_error(500, 'Failed to place bet.', $e->getMessage());
    }
} elseif ($endpoint === 'get_user_bets') {
    try {
        $stmt = $db->prepare("SELECT b.*, d.draw_number FROM bets b JOIN lottery_draws d ON b.draw_id = d.id WHERE b.user_id = ? ORDER BY b.created_at DESC");
        $stmt->execute([$user_id]);
        $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::send_json(['success' => true, 'bets' => $bets]);
    } catch (Exception $e) {
        Response::send_json_error(500, 'Failed to fetch bets.', $e->getMessage());
    }
} else {
    Response::send_json_error(404, 'Bet endpoint not found');
}
