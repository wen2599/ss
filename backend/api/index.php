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

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
require_once 'Router.php';
require_once 'Request.php';
require_once 'Response.php';

$db = get_db();
$request = new Request();
$router = new Router();

// --- Auth Endpoints ---
$router->add_route('POST', 'register', 'endpoints/auth.php');
$router->add_route('POST', 'login', 'endpoints/auth.php');
$router->add_route('POST', 'logout', 'endpoints/auth.php');
$router->add_route('GET', 'check_session', 'endpoints/auth.php');

// --- User Endpoints ---
$router->add_route('GET', 'find_user', 'endpoints/user.php');
$router->add_route('POST', 'transfer_points', 'endpoints/user.php');

// --- Draw Endpoints ---
$router->add_route('GET', 'get_draws', 'endpoints/draws.php');
$router->add_route('POST', 'create_draw', 'endpoints/draws.php');

// --- Bet Endpoints ---
$router->add_route('POST', 'place_bet', 'endpoints/bets.php');
$router->add_route('GET', 'get_user_bets', 'endpoints/bets.php');

// --- Settlement Endpoints ---
$router->add_route('POST', 'settle_draw', 'endpoints/settlements.php');

// --- Friends Endpoints ---
$router->add_route('POST', 'add_friend', 'endpoints/friends.php');
$router->add_route('POST', 'accept_friend', 'endpoints/friends.php');
$router->add_route('GET', 'get_friends', 'endpoints/friends.php');

// --- Leaderboard Endpoints ---
$router->add_route('GET', 'get_leaderboard', 'endpoints/leaderboard.php');


$router->dispatch($request->method, $request->endpoint);
