<?php
/**
 * Main API entry point and router for the application.
 *
 * This script handles incoming API requests, sets up the environment,
 * and routes the request to the appropriate endpoint handler.
 */
// Whitelist of allowed origins
$allowed_origins = [
    'http://localhost:3000', // For local development
    'https://ss.wenxiuxiu.eu.org'  // For production frontend
];

// Set CORS headers dynamically based on the request origin
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
} else {
    // Fallback or default origin, if needed. For security, it's often better
    // to not send the header at all if the origin is not in the whitelist.
    // However, to maintain original behavior for other potential clients,
    // we can keep a default.
    header('Access-Control-Allow-Origin: http://localhost:3000');
}
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
$draw_endpoints = ['get_draws', 'create_draw'];
$bet_endpoints = ['place_bet', 'get_user_bets'];
$settlement_endpoints = ['settle_draw'];
$friend_endpoints = ['add_friend', 'accept_friend', 'get_friends'];
$leaderboard_endpoints = ['get_leaderboard'];

if (in_array($endpoint, $auth_endpoints)) {
    require_once 'endpoints/auth.php';
} elseif (in_array($endpoint, $user_endpoints)) {
    require_once 'endpoints/user.php';
} elseif (in_array($endpoint, $draw_endpoints)) {
    require_once 'endpoints/draws.php';
} elseif (in_array($endpoint, $bet_endpoints)) {
    require_once 'endpoints/bets.php';
} elseif (in_array($endpoint, $settlement_endpoints)) {
    require_once 'endpoints/settlements.php';
} elseif (in_array($endpoint, $friend_endpoints)) {
    require_once 'endpoints/friends.php';
} elseif (in_array($endpoint, $leaderboard_endpoints)) {
    require_once 'endpoints/leaderboard.php';
} else {
    send_json_error(404, 'Endpoint not found');
}
