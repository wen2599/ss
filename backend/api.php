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
session_start();

require_once 'game.php';
require_once 'utils.php';

// Use a consistent way to manage rooms state, e.g., via session
$rooms = isset($_SESSION['rooms']) ? $_SESSION['rooms'] : [];

// Get the requested API endpoint
$request_uri = $_SERVER['REQUEST_URI'];
$api_endpoint = str_replace('/api', '', $request_uri);

// Get the request method (GET, POST, etc.)
$request_method = $_SERVER['REQUEST_METHOD'];

// Handle different API endpoints
switch ($api_endpoint) {
    case '/': // Default endpoint or info endpoint
        echo json_encode(['status' => 'success', 'message' => 'Welcome to the Dou Dizhu API']);
        break;
    case '/create_room':
        if ($request_method === 'POST') {
            $room_id = uniqid('room_');
            $rooms[$room_id] = [
                'id' => $room_id,
                'players' => [],
                'state' => 'waiting',
                'discarded_cards' => [],
            ];
            $player_id = uniqid('player_');
            $rooms[$room_id]['players'][$player_id] = ['id' => $player_id, 'name' => 'Creator'];
            $_SESSION['rooms'] = $rooms;
            echo json_encode(['success' => true, 'message' => 'Room created and joined', 'roomId' => $room_id, 'playerId' => $player_id]);
        } else {
            $_SESSION['rooms'] = $rooms;
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        }
        break;

    case '/join_room':
        if ($request_method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $room_id = $data['room_id'] ?? $data['roomId'] ?? null;
            $player_name = $data['player_name'] ?? $data['playerName'] ?? 'Guest';

            if ($room_id && isset($rooms[$room_id])) {
                if (count($rooms[$room_id]['players']) < 3) {
                    $player_id = uniqid('player_');
                    $rooms[$room_id]['players'][$player_id] = ['id' => $player_id, 'name' => $player_name];
                    $_SESSION['rooms'] = $rooms;
                    echo json_encode(['success' => true, 'message' => 'Joined room', 'roomId' => $room_id, 'playerId' => $player_id]);
                } else {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Room is full']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Room not found']);
            }
        } else {
            $_SESSION['rooms'] = $rooms;
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        }
        break;

    case '/play_card':
        if ($request_method === 'POST') {
            echo json_encode(['success' => true, 'message' => 'Card played']);
        } else {
            $_SESSION['rooms'] = $rooms;
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        }
        break;

    case '/get_room_state':
        if ($request_method === 'GET') {
            $room_id = $_GET['room_id'] ?? $_GET['roomId'] ?? null;
            if ($room_id && isset($rooms[$room_id])) {
                $roomState = $rooms[$room_id];
                if (isset($_GET['player_id']) || isset($_GET['playerId'])) {
                    $requestingPlayerId = $_GET['player_id'] ?? $_GET['playerId'];
                    foreach ($roomState['players'] as $playerId => &$playerData) {
                        if ($playerId !== $requestingPlayerId) {
                            unset($playerData['hand']);
                        }
                    }
                }
                echo json_encode(['success' => true, 'room' => $roomState]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Room not found']);
            }
        } else {
            $_SESSION['rooms'] = $rooms;
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        break;
}
?>
