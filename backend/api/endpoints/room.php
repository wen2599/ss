<?php
/**
 * Handles room-related endpoints:
 * - /matchmake
 * - /get_room_state
 */
switch ($endpoint) {
    case 'matchmake':
        if ($request_method !== 'POST') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        if (!isset($_SESSION['user_id'])) send_json_error(401, '请先登录');
        $user_id = $_SESSION['user_id'];
        $game_mode = $data['game_mode'] ?? null;
        if (!in_array($game_mode, ['normal_2', 'normal_5', 'double_2', 'double_5'])) {
            send_json_error(400, '无效的游戏模式');
        }
        $db->begin_transaction();
        try {
            $stmt = $db->prepare("SELECT r.id, COUNT(rp.id) as player_count FROM rooms r LEFT JOIN room_players rp ON r.id = rp.room_id WHERE r.game_mode = ? AND r.state = 'waiting' GROUP BY r.id HAVING player_count < 4 ORDER BY r.created_at ASC LIMIT 1 FOR UPDATE");
            $stmt->bind_param('s', $game_mode);
            $stmt->execute();
            $room = $stmt->get_result()->fetch_assoc();
            $room_id = null;
            if ($room) {
                $room_id = $room['id'];
                $seat = $room['player_count'] + 1;
                $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE user_id=user_id");
                $stmt->bind_param('iii', $room_id, $user_id, $seat);
                $stmt->execute();
            } else {
                $room_code = uniqid('room_');
                $created_at = date('Y-m-d H:i:s');
                $stmt = $db->prepare("INSERT INTO rooms (game_mode, room_code, state, created_at) VALUES (?, ?, 'waiting', ?)");
                $stmt->bind_param('sss', $game_mode, $room_code, $created_at);
                $stmt->execute();
                $room_id = $db->insert_id;
                $seat = 1;
                $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, ?, NOW())");
                $stmt->bind_param('iii', $room_id, $user_id, $seat);
                $stmt->execute();
            }
            $db->commit();
            echo json_encode(['success' => true, 'roomId' => $room_id]);
        } catch (Exception $e) {
            $db->rollback();
            send_json_error(500, '匹配失败: ' . $e->getMessage());
        }
        break;

    case 'get_room_state':
        if ($request_method !== 'GET') {
            send_json_error(405, 'Method Not Allowed');
            break;
        }
        $room_id = (int)($_GET['roomId'] ?? 0);
        if (!$room_id) send_json_error(400, 'Missing roomId');

        $last_state_hash = $_GET['lastStateHash'] ?? null;
        $timeout = 20; // 20 seconds timeout for long polling

        /**
         * Fetches the complete state of a room from the database.
         * @param mysqli $db The database connection.
         * @param int $room_id The ID of the room.
         * @return array|null The complete room state, or null if not found.
         */
        function get_full_room_state(mysqli $db, int $room_id): ?array {
            $stmt = $db->prepare("SELECT * FROM rooms WHERE id=?");
            $stmt->bind_param('i', $room_id);
            $stmt->execute();
            $room = $stmt->get_result()->fetch_assoc();
            if (!$room) return null;

            $stmt = $db->prepare("SELECT rp.*, u.display_id, u.points FROM room_players rp JOIN users u ON rp.user_id = u.id WHERE rp.room_id=? ORDER BY rp.seat");
            $stmt->bind_param('i', $room_id);
            $stmt->execute();
            $players_res = $stmt->get_result();
            $players = [];
            while ($row = $players_res->fetch_assoc()) {
                $p_data = ['id' => $row['user_id'], 'name' => '玩家 ' . $row['display_id'], 'seat' => $row['seat'], 'score' => $row['points']];
                if ($row['user_id'] == ($_SESSION['user_id'] ?? null) && $row['hand_cards']) {
                    $p_data['hand'] = json_decode($row['hand_cards'], true);
                }
                $players[] = $p_data;
            }

            $response = ['success' => true, 'room' => ['id' => $room['id'], 'state' => $room['state'], 'players' => $players, 'game_mode' => $room['game_mode']]];

            if ($room['state'] === 'playing' && $room['current_game_id']) {
                $game_id = $room['current_game_id'];
                $stmt = $db->prepare("SELECT * FROM games WHERE id=?");
                $stmt->bind_param('i', $game_id);
                $stmt->execute();
                $game = $stmt->get_result()->fetch_assoc();
                if ($game) {
                    $response['game'] = ['id' => $game['id'], 'state' => $game['game_state']];
                    $stmt = $db->prepare("SELECT * FROM player_hands WHERE game_id=?");
                    $stmt->bind_param('i', $game_id);
                    $stmt->execute();
                    $hands_res = $stmt->get_result();
                    $player_hands = [];
                    while($row = $hands_res->fetch_assoc()) {
                        $hand_data = ['playerId' => $row['player_id'], 'isSubmitted' => (bool)$row['is_submitted']];
                        if ($game['game_state'] === 'showdown' || $game['game_state'] === 'finished') {
                            $hand_data['isValid'] = (bool)$row['is_valid'];
                            $hand_data['front'] = json_decode($row['front_hand'], true);
                            $hand_data['middle'] = json_decode($row['middle_hand'], true);
                            $hand_data['back'] = json_decode($row['back_hand'], true);
                            $hand_data['scores'] = json_decode($row['score_details'], true);
                            $hand_data['roundScore'] = $row['round_score'];
                        }
                        $player_hands[] = $hand_data;
                    }
                    $response['game']['hands'] = $player_hands;
                }
            }
            return $response;
        }

        $start_time = time();
        while (time() - $start_time < $timeout) {
            $current_state = get_full_room_state($db, $room_id);
            if (!$current_state) {
                send_json_error(404, 'Room not found');
                break;
            }

            $current_state_hash = md5(json_encode($current_state));

            if ($current_state_hash !== $last_state_hash) {
                $current_state['state_hash'] = $current_state_hash;
                echo json_encode($current_state);
                break 2; // Break out of the while loop and the switch
            }

            sleep(1); // Wait for 1 second before checking again
        }

        // If loop finishes without change, send a no_change response
        echo json_encode(['success' => true, 'no_change' => true, 'state_hash' => $last_state_hash]);
        break;
}
