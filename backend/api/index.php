<?php
/**
 * Main API entry point and router for the Thirteen card game.
 *
 * This script handles incoming API requests, sets up the environment,
 * and routes the request to the appropriate endpoint handler.
 */
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
require_once 'game.php';

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

$auth_endpoints = ['register', 'login', 'logout', 'check_session'];
$user_endpoints = ['find_user', 'transfer_points'];
$room_endpoints = ['matchmake', 'get_room_state'];
$game_endpoints = ['start_game', 'submit_hand'];

if (in_array($endpoint, $auth_endpoints)) {
    require_once 'endpoints/auth.php';
} elseif (in_array($endpoint, $user_endpoints)) {
    require_once 'endpoints/user.php';
} elseif (in_array($endpoint, $room_endpoints)) {
    require_once 'endpoints/room.php';
} elseif (in_array($endpoint, $game_endpoints)) {
    require_once 'endpoints/game.php';
} else {
    send_json_error(404, 'Endpoint not found');
}
