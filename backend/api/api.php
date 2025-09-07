<?php
header('Access-Control-Allow-Origin: *'); // Allow all for simplicity, can be restricted later
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
require_once 'game.php'; // The new Thirteen game logic
require_once 'utils.php';

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
        case 'create_room':
            $room_code = uniqid('room_');
            $created_at = date('Y-m-d H:i:s');
            $stmt = $db->prepare("INSERT INTO rooms (room_code, state, created_at) VALUES (?, 'waiting', ?)");
            $stmt->bind_param('ss', $room_code, $created_at);
            if (!$stmt->execute()) send_json_error(500, 'Failed to create room.');
            $room_id = $db->insert_id;

            $player_id = uniqid('player_');
            $seat = 1;
            $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param('isi', $room_id, $player_id, $seat);
            if (!$stmt->execute()) send_json_error(500, 'Failed to add player to room.');

            echo json_encode(['success' => true, 'roomId' => $room_id, 'playerId' => $player_id]);
            break;

        case 'join_room':
            $room_id = (int)($data['roomId'] ?? 0);
            if (!$room_id) send_json_error(400, 'Missing roomId');

            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM room_players WHERE room_id=?");
            $stmt->bind_param('i', $room_id);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['cnt'];
            if ($count >= 4) send_json_error(403, 'Room is full.');

            $player_id = uniqid('player_');
            $seat = $count + 1;
            $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param('isi', $room_id, $player_id, $seat);
            if (!$stmt->execute()) send_json_error(500, 'Failed to join room.');

            echo json_encode(['success' => true, 'roomId' => $room_id, 'playerId' => $player_id]);
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
                    $stmt->bind_param('sis', $hand_json, $room_id, $player_id);
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
                    $stmt->bind_param('is', $game_id, $player_id);
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
            $game_id = (int)($data['gameId'] ?? 0);
            $player_id = $data['playerId'] ?? null;
            $front = $data['front'] ?? null;
            $middle = $data['middle'] ?? null;
            $back = $data['back'] ?? null;

            if (!$game_id || !$player_id || !$front || !$middle || !$back) {
                send_json_error(400, 'Missing required parameters.');
            }

            $db->begin_transaction();
            try {
                $analyzer = new ThirteenCardAnalyzer();
                $front_details = $analyzer->analyze_hand($front);
                $middle_details = $analyzer->analyze_hand($middle);
                $back_details = $analyzer->analyze_hand($back);

                $is_valid = $analyzer->compare_hands($back_details, $middle_details) >= 0 &&
                            $analyzer->compare_hands($middle_details, $front_details) >= 0;

                $stmt = $db->prepare("UPDATE player_hands SET is_submitted=1, is_valid=?, front_hand=?, middle_hand=?, back_hand=?, front_hand_details=?, middle_hand_details=?, back_hand_details=? WHERE game_id=? AND player_id=?");
                $stmt->bind_param('issssssis',
                    $is_valid,
                    json_encode($front), json_encode($middle), json_encode($back),
                    json_encode($front_details), json_encode($middle_details), json_encode($back_details),
                    $game_id, $player_id
                );
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
                    // SHOWDOWN
                    $stmt = $db->prepare("SELECT * FROM player_hands WHERE game_id=?");
                    $stmt->bind_param('i', $game_id);
                    $stmt->execute();
                    $hands_res = $stmt->get_result();
                    $all_hands = [];
                    while($row = $hands_res->fetch_assoc()) {
                        $all_hands[$row['player_id']] = [
                            'isValid' => (bool)$row['is_valid'],
                            'front' => json_decode($row['front_hand_details'], true),
                            'middle' => json_decode($row['middle_hand_details'], true),
                            'back' => json_decode($row['back_hand_details'], true)
                        ];
                    }

                    // Calculate royalties for each player first
                    $player_royalties = [];
                    foreach($all_hands as $pid => $hand) {
                        if ($hand['isValid']) {
                            $player_royalties[$pid] = $analyzer->calculate_royalties($hand['front'], $hand['middle'], $hand['back']);
                        } else {
                            $player_royalties[$pid] = ['total' => 0, 'front' => 0, 'middle' => 0, 'back' => 0];
                        }
                    }

                    // Calculate comparison scores
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
                                $score_diff = -6; // p1 scooped, a common scoring rule
                            } elseif ($p1_hand['isValid'] && !$p2_hand['isValid']) {
                                $score_diff = 6; // p2 scooped
                            } elseif ($p1_hand['isValid'] && $p2_hand['isValid']) {
                                $score_diff += $analyzer->compare_hands($p1_hand['front'], $p2_hand['front']);
                                $score_diff += $analyzer->compare_hands($p1_hand['middle'], $p2_hand['middle']);
                                $score_diff += $analyzer->compare_hands($p1_hand['back'], $p2_hand['back']);
                            }
                            $player_comparison_scores[$p1_id] += $score_diff;
                            $player_comparison_scores[$p2_id] -= $score_diff;
                        }
                    }

                    // Combine comparison scores and royalties, and update DB
                    $stmt_update_hand = $db->prepare("UPDATE player_hands SET round_score=?, score_details=? WHERE game_id=? AND player_id=?");
                    $stmt_update_total_score = $db->prepare("UPDATE room_players SET score = score + ? WHERE user_id = ? AND room_id = (SELECT room_id FROM games WHERE id=?)");

                    foreach($player_comparison_scores as $pid => $comp_score) {
                        // Each player's royalty score is paid by every other player
                        $total_royalty_payout = 0;
                        foreach ($player_ids as $other_pid) {
                            if ($pid !== $other_pid) {
                                $total_royalty_payout += ($player_royalties[$pid]['total'] - $player_royalties[$other_pid]['total']);
                            }
                        }

                        $final_round_score = $comp_score + $total_royalty_payout;

                        $score_details = json_encode([
                            'comparison_score' => $comp_score,
                            'royalty_details' => $player_royalties[$pid],
                            'total_royalty_payout' => $total_royalty_payout,
                            'final_score' => $final_round_score
                        ]);

                        $stmt_update_hand->bind_param('isis', $final_round_score, $score_details, $game_id, $pid);
                        $stmt_update_hand->execute();

                        $stmt_update_total_score->bind_param('isi', $final_round_score, $pid, $game_id);
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
        case 'get_room_state':
            $room_id = (int)($_GET['roomId'] ?? 0);
            $player_id_requesting = $_GET['playerId'] ?? null;
            if (!$room_id) send_json_error(400, 'Missing roomId');

            $stmt = $db->prepare("SELECT * FROM rooms WHERE id=?");
            $stmt->bind_param('i', $room_id);
            $stmt->execute();
            $room = $stmt->get_result()->fetch_assoc();
            if (!$room) send_json_error(404, 'Room not found');

            $stmt = $db->prepare("SELECT * FROM room_players WHERE room_id=? ORDER BY seat");
            $stmt->bind_param('i', $room_id);
            $stmt->execute();
            $players_res = $stmt->get_result();
            $players = [];
            while ($row = $players_res->fetch_assoc()) {
                $p_data = [
                    'id' => $row['user_id'],
                    'name' => 'Player ' . $row['seat'],
                    'seat' => $row['seat'],
                    'score' => $row['score'],
                ];
                if ($row['user_id'] === $player_id_requesting && $row['hand_cards']) {
                    $p_data['hand'] = json_decode($row['hand_cards'], true);
                }
                $players[] = $p_data;
            }

            $response = ['success' => true, 'room' => ['id' => $room['id'], 'state' => $room['state'], 'players' => $players]];

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
