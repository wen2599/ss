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
// A more robust way to get the endpoint, assuming the api files are in /api/
$api_base_path = '/api';
$api_endpoint = str_replace($api_base_path, '', $request_uri);
// Handle query string
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
    if (!$stmt) {
        send_json_error(500, 'Database error: ' . $db->error);
    }
    $stmt->bind_param('ss', $room_code, $created_at);
    if (!$stmt->execute()) {
        send_json_error(500, 'Failed to create room: ' . $stmt->error);
    }
    $room_id = $db->insert_id;
    $stmt->close();

    $player_id = uniqid('player_');
    $seat = 1;
    $joined_at = date('Y-m-d H:i:s');

    $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        send_json_error(500, 'Database error: ' . $db->error);
    }
    $stmt->bind_param('isis', $room_id, $player_id, $seat, $joined_at);
    if (!$stmt->execute()) {
        // Here we should probably roll back the room creation
        send_json_error(500, 'Failed to add player to room: ' . $stmt->error);
    }
    $stmt->close();

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
    if (!$room_id) {
        send_json_error(400, 'Missing room_id');
    }
    $room_id = (int)$room_id; // Cast to integer for safety

    // 检查房间是否存在且为等待状态
    $stmt = $db->prepare("SELECT id FROM rooms WHERE id=? AND state='waiting'");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        send_json_error(404, 'Room not found or not available');
    }
    $stmt->close();

    // 检查房间人数
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM room_players WHERE room_id=?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($count >= 3) {
        send_json_error(403, 'Room is full');
    }

    $player_id = uniqid('player_');
    $seat = $count + 1;
    $joined_at = date('Y-m-d H:i:s');

    $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, seat, joined_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isis', $room_id, $player_id, $seat, $joined_at);
    if (!$stmt->execute()) {
        send_json_error(500, 'Failed to join room: ' . $stmt->error);
    }
    $stmt->close();

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
        send_json_error(400, 'Missing room_id');
    }
    $room_id = (int)$room_id;

    // 房间基本信息
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id=?");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $room_result = $stmt->get_result();
    $room = $room_result->fetch_assoc();
    $stmt->close();

    if (!$room) {
        send_json_error(404, 'Room not found');
    }

    // 玩家列表
    $stmt = $db->prepare("SELECT * FROM room_players WHERE room_id=? ORDER BY seat ASC");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $players_res = $stmt->get_result();
    $players = [];
    while ($row = $players_res->fetch_assoc()) {
        $player_data = [
            'id' => $row['user_id'],
            'name' => $row['user_id'], // Placeholder for player name
            'seat' => $row['seat'],
            'isLandlord' => (bool)($row['is_landlord'] ?? false),
            'score' => $row['score'] ?? 0,
            'hand' => [],
            'hand_count' => isset($row['hand_cards']) ? count(json_decode($row['hand_cards'], true)) : 0
        ];
        // 只返回自己的手牌
        if ($player_id && $row['user_id'] === $player_id && !empty($row['hand_cards'])) {
            $player_data['hand'] = json_decode($row['hand_cards'], true);
        }
        $players[$row['user_id']] = $player_data;
    }
    $stmt->close();

    $response = [
        'id' => $room['id'],
        'state' => $room['state'],
        'players' => $players,
    ];

    // 如果游戏正在进行，则添加游戏状态
    if ($room['state'] === 'playing' && $room['current_game_id']) {
        $stmt = $db->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->bind_param('i', $room['current_game_id']);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($game) {
            $response['game_state'] = $game['game_state'];
            $response['landlord_id'] = $game['landlord_id'];
            $response['current_turn_player_id'] = $game['current_turn_player_id'];
            $response['bottom_cards'] = json_decode($game['bottom_cards'], true);

            $last_played_cards = [];
            if ($game['last_play_id']) {
                $stmt = $db->prepare("SELECT cards_played FROM plays WHERE id = ?");
                $stmt->bind_param('i', $game['last_play_id']);
                $stmt->execute();
                $last_play = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($last_play) {
                    $last_played_cards = json_decode($last_play['cards_played'], true);
                }
            }
            $response['last_played_cards'] = $last_played_cards;
        }
    }

    echo json_encode(['success' => true, 'room' => $response]);
    exit();
}

// 开始游戏
if ($api_endpoint === '/start_game' && $request_method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['room_id'] ?? null;
    if (!$room_id) {
        send_json_error(400, 'Missing room_id');
    }
    $room_id = (int)$room_id;

    $db->begin_transaction();

    try {
        // 1. 验证房间状态和人数
        $stmt = $db->prepare("SELECT state FROM rooms WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $room = $stmt->get_result()->fetch_assoc();
        if (!$room || $room['state'] !== 'waiting') {
            throw new Exception('Room is not available to start a game.', 404);
        }

        $stmt = $db->prepare("SELECT user_id FROM room_players WHERE room_id = ?");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $players_res = $stmt->get_result();
        $players = [];
        while($row = $players_res->fetch_assoc()) {
            $players[] = $row['user_id'];
        }
        if (count($players) !== 3) {
            throw new Exception('Room does not have 3 players.', 400);
        }
        $stmt->close();

        // 2. 发牌
        $deck = shuffle_deck(create_deck());
        $hands = [[], [], []];
        for ($i = 0; $i < 17; $i++) {
            $hands[0][] = array_pop($deck);
            $hands[1][] = array_pop($deck);
            $hands[2][] = array_pop($deck);
        }
        $bottom_cards = $deck;

        // 3. 更新玩家手牌
        $stmt = $db->prepare("UPDATE room_players SET hand_cards = ? WHERE room_id = ? AND user_id = ?");
        foreach ($players as $i => $player_id) {
            $hand_json = json_encode($hands[$i]);
            $stmt->bind_param('sis', $hand_json, $room_id, $player_id);
            $stmt->execute();
        }
        $stmt->close();

        // 4. 创建新游戏
        $created_at = date('Y-m-d H:i:s');
        $bottom_cards_json = json_encode($bottom_cards);
        $first_bidder_id = $players[array_rand($players)];

        $stmt = $db->prepare("INSERT INTO games (room_id, game_state, bottom_cards, current_turn_player_id, created_at) VALUES (?, 'bidding', ?, ?, ?)");
        $stmt->bind_param('isss', $room_id, $bottom_cards_json, $first_bidder_id, $created_at);
        $stmt->execute();
        $game_id = $db->insert_id;
        $stmt->close();

        // 5. 更新房间状态
        $stmt = $db->prepare("UPDATE rooms SET state = 'playing', current_game_id = ? WHERE id = ?");
        $stmt->bind_param('ii', $game_id, $room_id);
        $stmt->execute();
        $stmt->close();

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Game started', 'gameId' => $game_id]);

    } catch (Exception $e) {
        $db->rollback();
        send_json_error($e->getCode() > 0 ? $e->getCode() : 500, $e->getMessage());
    }
    exit();
}

// 玩家出价
if ($api_endpoint === '/bid' && $request_method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $game_id = $data['game_id'] ?? null;
    $player_id = $data['player_id'] ?? null;
    $bid_value = $data['bid_value'] ?? null;

    if (!$game_id || !$player_id || !isset($bid_value)) {
        send_json_error(400, 'Missing required parameters: game_id, player_id, bid_value');
    }
    $game_id = (int)$game_id;
    $bid_value = (int)$bid_value;

    $db->begin_transaction();
    try {
        // 获取游戏和当前出价者信息
        $stmt = $db->prepare("SELECT * FROM games WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $game_id);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();
        if (!$game || $game['game_state'] !== 'bidding') {
            throw new Exception("Game not found or not in bidding state", 404);
        }
        if ($game['current_turn_player_id'] !== $player_id) {
            throw new Exception("It's not your turn to bid", 403);
        }

        // 获取房间所有玩家，以确定顺序
        $stmt = $db->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC");
        $stmt->bind_param('i', $game['room_id']);
        $stmt->execute();
        $players_res = $stmt->get_result();
        $players = [];
        while($row = $players_res->fetch_assoc()) {
            $players[] = $row['user_id'];
        }
        $stmt->close();

        $current_player_index = array_search($player_id, $players);
        $next_player_id = $players[($current_player_index + 1) % 3];

        // To track passes
        $bids_history = isset($game['bids_history']) ? json_decode($game['bids_history'], true) : [];
        $bids_history[$player_id] = $bid_value;

        $landlord_id = null;

        if ($bid_value > 0 && $bid_value <= 3 && $bid_value > $game['current_bid']) {
            // Valid bid
            if ($bid_value == 3) {
                $landlord_id = $player_id;
            } else {
                $stmt = $db->prepare("UPDATE games SET current_bid = ?, landlord_id = ?, current_turn_player_id = ?, bids_history = ? WHERE id = ?");
                $bids_history_json = json_encode($bids_history);
                $stmt->bind_param('isssi', $bid_value, $player_id, $next_player_id, $bids_history_json, $game_id);
                $stmt->execute();
            }
        } elseif ($bid_value === 0) {
            // Pass
            $non_zero_bids = array_filter($bids_history, function($v) { return $v > 0; });
            // If two players have passed after a bid, the bidder wins.
            if (count($bids_history) === 3 && count($non_zero_bids) === 1) {
                 $landlord_id = array_keys($non_zero_bids)[0];
            } else {
                $stmt = $db->prepare("UPDATE games SET current_turn_player_id = ?, bids_history = ? WHERE id = ?");
                $bids_history_json = json_encode($bids_history);
                $stmt->bind_param('ssi', $next_player_id, $bids_history_json, $game_id);
                $stmt->execute();
            }
        } else {
            throw new Exception("Invalid bid value", 400);
        }

        // If a landlord is decided, end bidding and start the game
        if ($landlord_id) {
            // 1. Set landlord
            $stmt = $db->prepare("UPDATE room_players SET is_landlord = 1 WHERE room_id = ? AND user_id = ?");
            $stmt->bind_param('is', $game['room_id'], $landlord_id);
            $stmt->execute();

            // 2. Get landlord's hand and bottom cards
            $stmt = $db->prepare("SELECT hand_cards FROM room_players WHERE user_id = ?");
            $stmt->bind_param('s', $landlord_id);
            $stmt->execute();
            $landlord_hand = json_decode($stmt->get_result()->fetch_assoc()['hand_cards'], true);
            $bottom_cards = json_decode($game['bottom_cards'], true);

            // 3. Add bottom cards to hand
            $new_hand = array_merge($landlord_hand, $bottom_cards);
            $new_hand_json = json_encode($new_hand);
            $stmt = $db->prepare("UPDATE room_players SET hand_cards = ? WHERE user_id = ?");
            $stmt->bind_param('ss', $new_hand_json, $landlord_id);
            $stmt->execute();

            // 4. Update game state
            $stmt = $db->prepare("UPDATE games SET game_state = 'playing', landlord_id = ?, current_turn_player_id = ? WHERE id = ?");
            $stmt->bind_param('ssi', $landlord_id, $landlord_id, $game_id);
            $stmt->execute();
        }
        $stmt->close();

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Bid submitted successfully']);

    } catch (Exception $e) {
        $db->rollback();
        send_json_error($e->getCode() > 0 ? $e->getCode() : 500, $e->getMessage());
    }
    exit();
}

// 玩家过牌
if ($api_endpoint === '/pass_turn' && $request_method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $game_id = $data['game_id'] ?? null;
    $player_id = $data['player_id'] ?? null;

    if (!$game_id || !$player_id) {
        send_json_error(400, 'Missing required parameters: game_id, player_id');
    }
    $game_id = (int)$game_id;

    $db->begin_transaction();
    try {
        // 获取游戏信息
        $stmt = $db->prepare("SELECT * FROM games WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $game_id);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();
        if (!$game || $game['game_state'] !== 'playing') {
            throw new Exception("Game not found or not in playing state", 404);
        }
        if ($game['current_turn_player_id'] !== $player_id) {
            throw new Exception("It's not your turn", 403);
        }

        // 获取房间所有玩家，以确定顺序
        $stmt = $db->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC");
        $stmt->bind_param('i', $game['room_id']);
        $stmt->execute();
        $players_res = $stmt->get_result();
        $players = [];
        while($row = $players_res->fetch_assoc()) {
            $players[] = $row['user_id'];
        }
        $stmt->close();

        $current_player_index = array_search($player_id, $players);
        $next_player_id = $players[($current_player_index + 1) % 3];

        // 更新回合
        $stmt = $db->prepare("UPDATE games SET current_turn_player_id = ? WHERE id = ?");
        $stmt->bind_param('si', $next_player_id, $game_id);
        $stmt->execute();
        $stmt->close();

        // TODO: 记录pass，如果连续两个pass，则上一个出牌者可以任意出牌

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Turn passed']);

    } catch (Exception $e) {
        $db->rollback();
        send_json_error($e->getCode() > 0 ? $e->getCode() : 500, $e->getMessage());
    }
    exit();
}

// 玩家出牌
if ($api_endpoint === '/play_cards' && $request_method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $game_id = $data['game_id'] ?? null;
    $player_id = $data['player_id'] ?? null;
    $cards = $data['cards'] ?? null;

    if (!$game_id || !$player_id || !is_array($cards) || empty($cards)) {
        send_json_error(400, 'Missing or invalid parameters');
    }
    $game_id = (int)$game_id;

    $db->begin_transaction();
    try {
        // 获取游戏和玩家信息
        $stmt = $db->prepare("SELECT * FROM games WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $game_id);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();
        if (!$game || $game['game_state'] !== 'playing') {
            throw new Exception("Game not found or not in playing state", 404);
        }
        if ($game['current_turn_player_id'] !== $player_id) {
            throw new Exception("It's not your turn", 403);
        }

        $stmt = $db->prepare("SELECT hand_cards FROM room_players WHERE room_id = ? AND user_id = ?");
        $stmt->bind_param('is', $game['room_id'], $player_id);
        $stmt->execute();
        $player_data = $stmt->get_result()->fetch_assoc();
        $hand = json_decode($player_data['hand_cards'], true);

        // 1. 验证手牌中是否有这些卡
        if (count(array_intersect($cards, $hand)) !== count($cards)) {
            throw new Exception("Invalid cards: You don't have these cards in your hand", 400);
        }

        // 2. 验证出牌是否有效
        $last_played_cards = [];
        if ($game['last_play_id']) {
            $stmt_last_play = $db->prepare("SELECT cards_played FROM plays WHERE id = ?");
            $stmt_last_play->bind_param('i', $game['last_play_id']);
            $stmt_last_play->execute();
            $last_play_res = $stmt_last_play->get_result()->fetch_assoc();
            if($last_play_res) {
                $last_played_cards = json_decode($last_play_res['cards_played'], true);
            }
            $stmt_last_play->close();
        }

        $move = validate_move($cards, $last_played_cards);
        if (!$move) {
            throw new Exception("Invalid move", 400);
        }

        // 3. 更新玩家手牌
        $new_hand = array_values(array_diff($hand, $cards));
        $new_hand_json = json_encode($new_hand);
        $stmt = $db->prepare("UPDATE room_players SET hand_cards = ? WHERE room_id = ? AND user_id = ?");
        $stmt->bind_param('sis', $new_hand_json, $game['room_id'], $player_id);
        $stmt->execute();

        // 4. 记录出牌
        $cards_json = json_encode($cards);
        $created_at = date('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO plays (game_id, player_id, cards_played, play_type, play_value, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssis', $game_id, $player_id, $cards_json, $move['type'], $move['value'], $created_at);
        $stmt->execute();
        $play_id = $db->insert_id;

        // 5. 检查胜利条件
        if (count($new_hand) === 0) {
            // Player has won
            $stmt = $db->prepare("UPDATE games SET game_state = 'finished', winner_id = ? WHERE id = ?");
            $stmt->bind_param('si', $player_id, $game_id);
            $stmt->execute();

            $stmt = $db->prepare("UPDATE rooms SET state = 'finished' WHERE id = ?");
            $stmt->bind_param('i', $game['room_id']);
            $stmt->execute();
        } else {
            // 6. 更新游戏状态
            $stmt_players = $db->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY seat ASC");
            $stmt_players->bind_param('i', $game['room_id']);
            $stmt_players->execute();
            $players_res = $stmt_players->get_result();
            $players = [];
            while($row = $players_res->fetch_assoc()) {
                $players[] = $row['user_id'];
            }
            $stmt_players->close();

            $current_player_index = array_search($player_id, $players);
            $next_player_id = $players[($current_player_index + 1) % 3];

            $stmt = $db->prepare("UPDATE games SET last_play_id = ?, current_turn_player_id = ? WHERE id = ?");
            $stmt->bind_param('isi', $play_id, $next_player_id, $game_id);
            $stmt->execute();
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Cards played successfully']);

    } catch (Exception $e) {
        $db->rollback();
        send_json_error($e->getCode() > 0 ? $e->getCode() : 500, $e->getMessage());
    }
    exit();
}

// 其它接口...
send_json_error(404, 'Endpoint not found');
?>
