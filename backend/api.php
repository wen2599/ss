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

// 获取请求路径
$request_uri = $_SERVER['REQUEST_URI'];
$api_endpoint = str_replace('/api', '', $request_uri);
$request_method = $_SERVER['REQUEST_METHOD'];

// 创建房间（并自动加入为第一个玩家）
if ($api_endpoint === '/create_room' && $request_method === 'POST') {
    $db = get_db();
    $room_code = uniqid('room_');
    $created_at = date('Y-m-d H:i:s');
    $db->query("INSERT INTO rooms (room_code, state, created_at) VALUES ('$room_code', 'waiting', '$created_at')");
    $room_id = $db->insert_id;

    $player_name = 'Creator';
    $player_id = uniqid('player_');
    $seat = 1;
    $joined_at = date('Y-m-d H:i:s');
    $db->query("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES ($room_id, '$player_id', $seat, '$joined_at')");

    echo json_encode([
        'success' => true,
        'message' => 'Room created and joined',
        'roomId' => $room_id,
        'playerId' => $player_id
    ]);
    exit();
}

// 加入房间
if ($api_endpoint === '/join_room' && $request_method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['room_id'] ?? $data['roomId'] ?? null;
    $player_name = $data['player_name'] ?? $data['playerName'] ?? 'Guest';
    if (!$room_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing room_id']);
        exit();
    }
    $db = get_db();
    // 检查房间是否存在且未满3人
    $res = $db->query("SELECT id FROM rooms WHERE id=$room_id AND state='waiting'");
    if (!$res->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Room not found or not available']);
        exit();
    }
    $count = $db->query("SELECT COUNT(*) as cnt FROM room_players WHERE room_id=$room_id")->fetch_assoc()['cnt'];
    if ($count >= 3) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Room is full']);
        exit();
    }
    $player_id = uniqid('player_');
    $seat = $count + 1;
    $joined_at = date('Y-m-d H:i:s');
    $db->query("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES ($room_id, '$player_id', $seat, '$joined_at')");
    echo json_encode([
        'success' => true,
        'message' => 'Joined room',
        'roomId' => $room_id,
        'playerId' => $player_id
    ]);
    exit();
}

// 获取房间状态
if ($api_endpoint === '/get_room_state' && $request_method === 'GET') {
    $room_id = $_GET['room_id'] ?? $_GET['roomId'] ?? null;
    $player_id = $_GET['player_id'] ?? $_GET['playerId'] ?? null;
    if (!$room_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing room_id']);
        exit();
    }
    $db = get_db();

    // 房间基本信息
    $room = $db->query("SELECT * FROM rooms WHERE id=$room_id")->fetch_assoc();
    if (!$room) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Room not found']);
        exit();
    }
    // 玩家列表
    $players_res = $db->query("SELECT * FROM room_players WHERE room_id=$room_id ORDER BY seat ASC");
    $players = [];
    while ($row = $players_res->fetch_assoc()) {
        $player = [
            'id' => $row['user_id'],
            'name' => $row['user_id'],
            'seat' => $row['seat'],
            'isLandlord' => (bool)$row['is_landlord'],
            'score' => $row['score'],
            'hand' => []
        ];
        // 只返回自己的手牌
        if ($player_id && $row['user_id'] === $player_id && $row['hand_cards']) {
            $player['hand'] = json_decode($row['hand_cards'], true);
        }
        $players[$row['user_id']] = $player;
    }

    echo json_encode([
        'success' => true,
        'room' => [
            'id' => $room['id'],
            'state' => $room['state'],
            'players' => $players,
            'discarded_cards' => [], // TODO: 查询 plays 表并转换
            'bottom_cards' => [], // TODO: 查询 games 表底牌
        ]
    ]);
    exit();
}

// 其它接口...
http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
exit();
?>
