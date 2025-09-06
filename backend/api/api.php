<?php
header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php'; // 数据库连接
require_once 'game.php';
require_once 'utils.php';

function send_json_error($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// 获取请求路径
$request_uri = $_SERVER['REQUEST_URI'];
$api_base_path = '/api';
$api_endpoint = str_replace($api_base_path, '', $request_uri);
if (false !== $pos = strpos($api_endpoint, '?')) {
    $api_endpoint = substr($api_endpoint, 0, $pos);
}

$request_method = $_SERVER['REQUEST_METHOD'];
$db = get_db();

// 创建房间
if ($api_endpoint === '/create_room' && $request_method === 'POST') {
    $room_code = uniqid('room_');
    $created_at = date('Y-m-d H:i:s');

    $stmt = $db->prepare("INSERT INTO rooms (room_code, state, created_at) VALUES (?, 'waiting', ?)");
    $stmt->bind_param('ss', $room_code, $created_at);
    $stmt->execute();
    $room_id = $db->insert_id;
    $stmt->close();

    $player_id = uniqid('player_');
    $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, seat) VALUES (?, ?, 1)");
    $stmt->bind_param('is', $room_id, $player_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'roomId' => $room_id, 'playerId' => $player_id]);
    exit();
}

// 加入房间
if ($api_endpoint === '/join_room' && $request_method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['roomId'] ?? null;
    if (!$room_id) send_json_error(400, 'Missing roomId');

    $room_id = (int)$room_id;

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM room_players WHERE room_id=?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($count >= 4) send_json_error(403, 'Room is full');

    $player_id = uniqid('player_');
    $seat = $count + 1;

    $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, seat) VALUES (?, ?, ?)");
    $stmt->bind_param('isi', $room_id, $player_id, $seat);
    $stmt->execute();
    $stmt->close();

    if ($seat === 4) {
        // Start game
        $stmt = $db->prepare("UPDATE rooms SET state = 'arranging' WHERE id = ?");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("SELECT user_id FROM room_players WHERE room_id = ?");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $players_res = $stmt->get_result();
        $players_ids = [];
        while($row = $players_res->fetch_assoc()) $players_ids[] = $row['user_id'];
        $stmt->close();

        $deck = shuffle_deck(create_deck());
        $hands = array_chunk($deck, 13);

        $stmt = $db->prepare("UPDATE room_players SET hand_cards = ? WHERE room_id = ? AND user_id = ?");
        foreach($players_ids as $idx => $pid) {
            $hand_json = json_encode($hands[$idx]);
            $stmt->bind_param('sis', $hand_json, $room_id, $pid);
            $stmt->execute();
        }
        $stmt->close();
    }

    echo json_encode(['success' => true, 'roomId' => $room_id, 'playerId' => $player_id]);
    exit();
}

// 获取房间状态
if ($api_endpoint === '/get_room_state' && $request_method === 'GET') {
    $room_id = $_GET['roomId'] ?? null;
    $player_id = $_GET['playerId'] ?? null;
    if (!$room_id) send_json_error(400, 'Missing roomId');
    $room_id = (int)$room_id;

    $stmt = $db->prepare("SELECT * FROM rooms WHERE id=?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$room) send_json_error(404, 'Room not found');

    $stmt = $db->prepare("SELECT * FROM room_players WHERE room_id=? ORDER BY seat ASC");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $players_res = $stmt->get_result();
    $players = [];
    while ($row = $players_res->fetch_assoc()) {
        $hand = json_decode($row['hand_cards'] ?? '[]', true);
        $player_data = [
            'id' => $row['user_id'],
            'name' => 'Player ' . $row['seat'],
            'hand_count' => count($hand),
            'hand_is_set' => (bool)($row['hand_is_set'] ?? false),
            'front_hand' => json_decode($row['front_hand'] ?? '[]', true),
            'middle_hand' => json_decode($row['middle_hand'] ?? '[]', true),
            'back_hand' => json_decode($row['back_hand'] ?? '[]', true),
        ];
        if ($player_id && $row['user_id'] === $player_id) {
            $player_data['hand'] = $hand;
        }
        $players[$row['user_id']] = $player_data;
    }
    $stmt->close();

    $response = [
        'id' => $room['id'],
        'state' => $room['state'],
        'players' => $players,
        'comparison_results' => json_decode($room['game_state'] ?? '[]', true)
    ];

    echo json_encode(['success' => true, 'room' => $response]);
    exit();
}

// 设置手牌
if ($api_endpoint === '/set_hand' && $request_method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['room_id'] ?? null;
    $player_id = $data['player_id'] ?? null;
    $front_hand = $data['front_hand'] ?? null;
    $middle_hand = $data['middle_hand'] ?? null;
    $back_hand = $data['back_hand'] ?? null;

    if (!$room_id || !$player_id || !$front_hand || !$middle_hand || !$back_hand) {
        send_json_error(400, 'Missing parameters');
    }

    $stmt = $db->prepare("UPDATE room_players SET front_hand=?, middle_hand=?, back_hand=?, hand_is_set=1 WHERE room_id=? AND user_id=?");
    $stmt->bind_param('sssis', json_encode($front_hand), json_encode($middle_hand), json_encode($back_hand), $room_id, $player_id);
    $stmt->execute();
    $stmt->close();

    // 检查是否所有玩家都已设置手牌
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM room_players WHERE room_id=? AND hand_is_set=1");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $ready_count = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($ready_count == 4) {
        // All players ready, compare hands
        $stmt = $db->prepare("SELECT * FROM room_players WHERE room_id=?");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $players_res = $stmt->get_result();
        $players_data = [];
        while($row = $players_res->fetch_assoc()) $players_data[] = $row;
        $stmt->close();

        $results = [];
        $player_scores = array_fill_keys(array_column($players_data, 'user_id'), 0);

        for ($i=0; $i<4; $i++) {
            for ($j=$i+1; $j<4; $j++) {
                $p1 = $players_data[$i];
                $p2 = $players_data[$j];

                $p1_id = $p1['user_id'];
                $p2_id = $p2['user_id'];

                $p1_front = Thirteen_CardAnalyzer::analyze_hand(json_decode($p1['front_hand'], true));
                $p1_middle = Thirteen_CardAnalyzer::analyze_hand(json_decode($p1['middle_hand'], true));
                $p1_back = Thirteen_CardAnalyzer::analyze_hand(json_decode($p1['back_hand'], true));

                $p2_front = Thirteen_CardAnalyzer::analyze_hand(json_decode($p2['front_hand'], true));
                $p2_middle = Thirteen_CardAnalyzer::analyze_hand(json_decode($p2['middle_hand'], true));
                $p2_back = Thirteen_CardAnalyzer::analyze_hand(json_decode($p2['back_hand'], true));

                $score = 0;
                $score += Thirteen_CardAnalyzer::compare_hands($p1_front, $p2_front);
                $score += Thirteen_CardAnalyzer::compare_hands($p1_middle, $p2_middle);
                $score += Thirteen_CardAnalyzer::compare_hands($p1_back, $p2_back);

                $results["{$p1_id}_vs_{$p2_id}"] = $score;
                $player_scores[$p1_id] += $score;
                $player_scores[$p2_id] -= $score;
            }
        }

        $stmt = $db->prepare("UPDATE rooms SET state='finished', game_state=? WHERE id=?");
        $stmt->bind_param('si', json_encode($results), $room_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("UPDATE room_players SET score = score + ? WHERE room_id = ? AND user_id = ?");
        foreach($player_scores as $player_id => $score_change) {
            $stmt->bind_param('iis', $score_change, $room_id, $player_id);
            $stmt->execute();
        }
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Hand set successfully']);
    exit();
}


send_json_error(404, 'Endpoint not found');
?>
