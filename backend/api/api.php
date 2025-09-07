<?php
header('Access-Control-Allow-Origin: http://localhost:3000'); // Adjust for your frontend URL
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

session_start();

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
require_once 'game.php'; // The new Thirteen game logic

function send_json_error($code, $message, $details = null) {
    http_response_code($code);
    $response = ['success' => false, 'message' => $message];
    if ($details) {
        $response['details'] = $details;
    }
    echo json_encode($response);
    exit();
}

$db = get_db();
$endpoint = $_GET['endpoint'] ?? '';
$request_method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];

if ($request_method === 'POST') {
    switch ($endpoint) {
        case 'register':
            $phone = $data['phone'] ?? null;
            $password = $data['password'] ?? null;
            if (!$phone || !$password) send_json_error(400, '手机号和密码不能为空');
            if (strlen($password) < 6) send_json_error(400, '密码长度不能少于6位');
            $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) send_json_error(409, '该手机号已被注册');
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $display_id = null;
            do {
                $display_id = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("SELECT id FROM users WHERE display_id = ?");
                $stmt->bind_param('s', $display_id);
                $stmt->execute();
            } while ($stmt->get_result()->num_rows > 0);
            $stmt = $db->prepare("INSERT INTO users (display_id, phone_number, password_hash) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $display_id, $phone, $password_hash);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => '注册成功', 'displayId' => $display_id]);
            } else {
                send_json_error(500, '注册失败，请稍后再试');
            }
            break;

        case 'login':
            $phone = $data['phone'] ?? null;
            $password = $data['password'] ?? null;
            if (!$phone || !$password) send_json_error(400, '手机号和密码不能为空');
            $stmt = $db->prepare("SELECT id, display_id, password_hash, points FROM users WHERE phone_number = ?");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['display_id'] = $user['display_id'];
                $_SESSION['points'] = $user['points'];
                echo json_encode(['success' => true, 'user' => ['id' => $user['id'], 'displayId' => $user['display_id'], 'points' => $user['points']]]);
            } else {
                send_json_error(401, '手机号或密码错误');
            }
            break;

        case 'logout':
            session_destroy();
            echo json_encode(['success' => true, 'message' => '已成功登出']);
            break;

        case 'transfer_points':
            if (!isset($_SESSION['user_id'])) send_json_error(401, '请先登录');
            $sender_id = $_SESSION['user_id'];
            $recipient_display_id = $data['recipientId'] ?? null;
            $amount = (int)($data['amount'] ?? 0);
            if (!$recipient_display_id || $amount <= 0) send_json_error(400, '无效的接收人ID或金额');
            $db->begin_transaction();
            try {
                $stmt = $db->prepare("SELECT points FROM users WHERE id = ? FOR UPDATE");
                $stmt->bind_param('i', $sender_id);
                $stmt->execute();
                $sender = $stmt->get_result()->fetch_assoc();
                if (!$sender || $sender['points'] < $amount) throw new Exception('积分不足');
                $stmt = $db->prepare("SELECT id FROM users WHERE display_id = ? FOR UPDATE");
                $stmt->bind_param('s', $recipient_display_id);
                $stmt->execute();
                $recipient = $stmt->get_result()->fetch_assoc();
                if (!$recipient) throw new Exception('接收人ID不存在');
                $recipient_id = $recipient['id'];
                if ($sender_id === $recipient_id) throw new Exception('不能给自己赠送积分');
                $stmt = $db->prepare("UPDATE users SET points = points - ? WHERE id = ?");
                $stmt->bind_param('ii', $amount, $sender_id);
                $stmt->execute();
                $stmt = $db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $stmt->bind_param('ii', $amount, $recipient_id);
                $stmt->execute();
                $db->commit();
                $_SESSION['points'] -= $amount;
                echo json_encode(['success' => true, 'message' => "成功赠送 {$amount} 积分"]);
            } catch (Exception $e) {
                $db->rollback();
                send_json_error(400, $e->getMessage());
            }
            break;

        case 'matchmake':
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

        case 'start_game':
            $room_id = (int)($data['roomId'] ?? 0);
            if (!$room_id) send_json_error(400, 'Missing roomId');
            $db->begin_transaction();
            try {
                $stmt = $db->prepare("SELECT user_id FROM room_players WHERE room_id = ?");
                $stmt->bind_param('i', $room_id);
                $stmt->execute();
                $players_res = $stmt->get_result();
                $players = [];
                while($row = $players_res->fetch_assoc()) $players[] = $row['user_id'];
                if (count($players) < 2) throw new Exception('Not enough players (min 2).');
                $deck = shuffle_deck(create_deck());
                $hands = array_fill_keys($players, []);
                for ($i = 0; $i < 13; $i++) {
                    foreach ($players as $player_id) {
                        $hands[$player_id][] = array_pop($deck);
                    }
                }
                $stmt = $db->prepare("UPDATE room_players SET hand_cards = ? WHERE room_id = ? AND user_id = ?");
                foreach ($players as $player_id) {
                    $hand_json = json_encode($hands[$player_id]);
                    $stmt->bind_param('sii', $hand_json, $room_id, $player_id);
                    $stmt->execute();
                }
                $stmt = $db->prepare("INSERT INTO games (room_id, game_state, created_at) VALUES (?, 'setting_hands', NOW())");
                $stmt->bind_param('i', $room_id);
                $stmt->execute();
                $game_id = $db->insert_id;
                $stmt = $db->prepare("UPDATE rooms SET state = 'playing', current_game_id = ? WHERE id = ?");
                $stmt->bind_param('ii', $game_id, $room_id);
                $stmt->execute();
                $stmt = $db->prepare("INSERT INTO player_hands (game_id, player_id) VALUES (?, ?)");
                foreach ($players as $player_id) {
                    $stmt->bind_param('ii', $game_id, $player_id);
                    $stmt->execute();
                }
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Game started', 'gameId' => $game_id]);
            } catch (Exception $e) {
                $db->rollback();
                send_json_error(500, 'Failed to start game: ' . $e->getMessage());
            }
            break;

        case 'submit_hand':
            if (!isset($_SESSION['user_id'])) send_json_error(401, '请先登录');
            $user_id = $_SESSION['user_id'];
            $game_id = (int)($data['gameId'] ?? 0);
            $front = $data['front'] ?? null;
            $middle = $data['middle'] ?? null;
            $back = $data['back'] ?? null;
            if (!$game_id || !$front || !$middle || !$back) send_json_error(400, 'Missing required parameters.');
            $db->begin_transaction();
            try {
                $analyzer = new ThirteenCardAnalyzer();
                $front_details = $analyzer->analyze_hand($front);
                $middle_details = $analyzer->analyze_hand($middle);
                $back_details = $analyzer->analyze_hand($back);
                $is_valid = $analyzer->compare_hands($back_details, $middle_details) >= 0 && $analyzer->compare_hands($middle_details, $front_details) >= 0;
                $stmt = $db->prepare("UPDATE player_hands SET is_submitted=1, is_valid=?, front_hand=?, middle_hand=?, back_hand=?, front_hand_details=?, middle_hand_details=?, back_hand_details=? WHERE game_id=? AND player_id=?");
                $stmt->bind_param('issssssii', $is_valid, json_encode($front), json_encode($middle), json_encode($back), json_encode($front_details), json_encode($middle_details), json_encode($back_details), $game_id, $user_id);
                $stmt->execute();
                $stmt = $db->prepare("SELECT COUNT(*) as submitted_count FROM player_hands WHERE game_id=? AND is_submitted=1");
                $stmt->bind_param('i', $game_id);
                $stmt->execute();
                $submitted_count = $stmt->get_result()->fetch_assoc()['submitted_count'];
                $stmt = $db->prepare("SELECT COUNT(*) as total_count FROM player_hands WHERE game_id=?");
                $stmt->bind_param('i', $game_id);
                $stmt->execute();
                $total_count = $stmt->get_result()->fetch_assoc()['total_count'];
                if ($submitted_count === $total_count) {
                    $stmt = $db->prepare("SELECT game_mode FROM rooms r JOIN games g ON r.id = g.room_id WHERE g.id = ?");
                    $stmt->bind_param('i', $game_id);
                    $stmt->execute();
                    $game_mode = $stmt->get_result()->fetch_assoc()['game_mode'] ?? 'normal_2';
                    $score_multipliers = ['normal_2' => ['base' => 2, 'double' => 1], 'normal_5' => ['base' => 5, 'double' => 1], 'double_2' => ['base' => 2, 'double' => 2], 'double_5' => ['base' => 5, 'double' => 2]];
                    $multiplier = $score_multipliers[$game_mode];
                    $stmt = $db->prepare("SELECT * FROM player_hands WHERE game_id=?");
                    $stmt->bind_param('i', $game_id);
                    $stmt->execute();
                    $hands_res = $stmt->get_result();
                    $all_hands = [];
                    while($row = $hands_res->fetch_assoc()) {
                        $all_hands[$row['player_id']] = ['isValid' => (bool)$row['is_valid'], 'front' => json_decode($row['front_hand_details'], true), 'middle' => json_decode($row['middle_hand_details'], true), 'back' => json_decode($row['back_hand_details'], true)];
                    }
                    $player_royalties = [];
                    foreach($all_hands as $pid => $hand) {
                        if ($hand['isValid']) {
                            $player_royalties[$pid] = $analyzer->calculate_royalties($hand['front'], $hand['middle'], $hand['back']);
                        } else {
                            $player_royalties[$pid] = ['total' => 0, 'front' => 0, 'middle' => 0, 'back' => 0];
                        }
                    }
                    $player_comparison_scores = array_fill_keys(array_keys($all_hands), 0);
                    $player_ids = array_keys($all_hands);
                    for ($i = 0; $i < count($player_ids); $i++) {
                        for ($j = $i + 1; $j < count($player_ids); $j++) {
                            $p1_id = $player_ids[$i];
                            $p2_id = $player_ids[$j];
                            $p1_hand = $all_hands[$p1_id];
                            $p2_hand = $all_hands[$p2_id];
                            $score_diff = 0;
                            if (!$p1_hand['isValid'] && $p2_hand['isValid']) {
                                $score_diff = -6;
                            } elseif ($p1_hand['isValid'] && !$p2_hand['isValid']) {
                                $score_diff = 6;
                            } elseif ($p1_hand['isValid'] && $p2_hand['isValid']) {
                                $score_diff += $analyzer->compare_hands($p1_hand['front'], $p2_hand['front']);
                                $score_diff += $analyzer->compare_hands($p1_hand['middle'], $p2_hand['middle']);
                                $score_diff += $analyzer->compare_hands($p1_hand['back'], $p2_hand['back']);
                            }
                            $player_comparison_scores[$p1_id] += $score_diff;
                            $player_comparison_scores[$p2_id] -= $score_diff;
                        }
                    }
                    $stmt_update_hand = $db->prepare("UPDATE player_hands SET round_score=?, score_details=? WHERE game_id=? AND player_id=?");
                    $stmt_update_total_score = $db->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                    foreach($player_comparison_scores as $pid => $comp_score) {
                        $total_royalty_payout = 0;
                        foreach ($player_ids as $other_pid) {
                            if ($pid !== $other_pid) {
                                $total_royalty_payout += ($player_royalties[$pid]['total'] - $player_royalties[$other_pid]['total']);
                            }
                        }
                        $final_round_score = ($comp_score * $multiplier['base'] * $multiplier['double']) + $total_royalty_payout;
                        $score_details = json_encode(['comparison_score' => $comp_score, 'base_points' => $multiplier['base'], 'double_factor' => $multiplier['double'], 'royalty_details' => $player_royalties[$pid], 'total_royalty_payout' => $total_royalty_payout, 'final_score' => $final_round_score]);
                        $stmt_update_hand->bind_param('isii', $final_round_score, $score_details, $game_id, $pid);
                        $stmt_update_hand->execute();
                        $stmt_update_total_score->bind_param('ii', $final_round_score, $pid);
                        $stmt_update_total_score->execute();
                    }
                    $stmt_update_hand->close();
                    $stmt_update_total_score->close();
                    $stmt = $db->prepare("UPDATE games SET game_state='finished' WHERE id=?");
                    $stmt->bind_param('i', $game_id);
                    $stmt->execute();
                }
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Hand submitted.']);
            } catch (Exception $e) {
                $db->rollback();
                send_json_error(500, 'Failed to submit hand: ' . $e->getMessage());
            }
            break;

        default:
            send_json_error(404, 'Endpoint not found');
    }
} elseif ($request_method === 'GET') {
    switch ($endpoint) {
        case 'check_session':
            if (isset($_SESSION['user_id'])) {
                echo json_encode(['success' => true, 'isAuthenticated' => true, 'user' => ['id' => $_SESSION['user_id'], 'displayId' => $_SESSION['display_id'], 'points' => $_SESSION['points']]]);
            } else {
                echo json_encode(['success' => true, 'isAuthenticated' => false]);
            }
            break;
        case 'find_user':
            if (!isset($_SESSION['user_id'])) send_json_error(401, '请先登录');
            $phone = $_GET['phone'] ?? null;
            if (!$phone) send_json_error(400, '需要提供手机号');
            $stmt = $db->prepare("SELECT display_id FROM users WHERE phone_number = ?");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                echo json_encode(['success' => true, 'user' => ['displayId' => $user['display_id']]]);
            } else {
                send_json_error(404, '未找到该用户');
            }
            break;
        case 'get_room_state':
            $room_id = (int)($_GET['roomId'] ?? 0);
            $player_id_requesting = $_GET['playerId'] ?? null; // This is now the session user_id
            if (!$room_id) send_json_error(400, 'Missing roomId');
            $stmt = $db->prepare("SELECT * FROM rooms WHERE id=?");
            $stmt->bind_param('i', $room_id);
            $stmt->execute();
            $room = $stmt->get_result()->fetch_assoc();
            if (!$room) send_json_error(404, 'Room not found');
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
            echo json_encode($response);
            break;
        default:
            send_json_error(404, 'Endpoint not found');
    }
} else {
    send_json_error(405, 'Method Not Allowed');
}
