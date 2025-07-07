php
<?php

// Basic entry point for the Dou Dizhu backend.
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org');
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();

// Placeholder for game logic and state management.
// This will eventually include handling game rooms, player connections, card dealing,
// game state updates, and win/loss conditions. This logic might be separated
// into different files or classes.

// Placeholder for API endpoints.
// This will handle incoming requests from the frontend, such as:
// - Joining a game room
// - Sending player actions (e.g., playing cards, calling the landlord)
// - Receiving game state updates
// API logic will be handled in api.php

// Include the API handler
require_once __DIR__ . '/api.php'; // Make sure api.php is in the same directory or adjust the path
require_once __DIR__ . '/game.php'; // Include game logic file (assuming it's needed)
require_once __DIR__ . '/utils.php'; // Include utility file (assuming it's needed)
require_once __DIR__ . '/config.php'; // Include config file (assuming it's needed)

// Basic routing based on request path
$request_uri = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$api_endpoint = end($request_uri); // Get the last part of the URL


// Handle API requests
switch ($api_endpoint) {
    case 'create_room':
        handleCreateRoom();
        break;
    case 'join_room':
        handleJoinRoom();
        break;
    case 'get_room_state':
        handleGetRoomState();
        break;
    // TODO: Add other API endpoints here (e.g., /play_card, /call_landlord)
    default:
        // Handle invalid endpoint
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

?>