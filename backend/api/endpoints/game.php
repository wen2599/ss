<?php
/**
 * Handles game-related endpoints:
 * - /start_game
 * - /submit_hand
 */
switch ($endpoint) {
    case 'start_game':
        if ($request_method !== 'POST') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        $room_id = (int)($data['roomId'] ?? 0);
        if (!$room_id) send_json_error(400, 'Missing roomId');
        $db->begin_transaction();
        try {
            $game_id = Game::startGame($db, $room_id);
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Game started', 'gameId' => $game_id]);
        } catch (Exception $e) {
            $db->rollback();
            send_json_error(500, 'Failed to start game: ' . $e->getMessage());
        }
        break;

    case 'submit_hand':
        if ($request_method !== 'POST') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (!isset($_SESSION['user_id'])) send_json_error(401, '请先登录');
        $user_id = $_SESSION['user_id'];
        $game_id = (int)($data['gameId'] ?? 0);
        $front = $data['front'] ?? null;
        $middle = $data['middle'] ?? null;
        $back = $data['back'] ?? null;
        if (!$game_id || !$front || !$middle || !$back) send_json_error(400, 'Missing required parameters.');
        $db->begin_transaction();
        try {
            Game::submitHand($db, $user_id, $game_id, $front, $middle, $back);
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Hand submitted.']);
        } catch (Exception $e) {
            $db->rollback();
            send_json_error(500, 'Failed to submit hand: ' . $e->getMessage());
        }
        break;
}
