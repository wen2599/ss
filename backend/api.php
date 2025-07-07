php
<?php

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// This file handles API requests from the frontend.
// Include necessary files
// require_once 'config.php';
// require_once 'game.php';
// require_once 'utils.php';
// Simple in-memory storage for rooms (for demonstration purposes)
$rooms = isset($_SESSION['rooms']) ? $_SESSION['rooms'] : [];

header('Content-Type: application/json');

// Get the requested API endpoint
$request_uri = $_SERVER['REQUEST_URI'];
$api_endpoint = str_replace('/api', '', $request_uri); // Assuming API requests start with /api

// Get the request method (GET, POST, etc.)
$request_method = $_SERVER['REQUEST_METHOD'];

// Handle different API endpoints
switch ($api_endpoint) {
    case '/': // Default endpoint or info endpoint
        echo json_encode(['status' => 'success', 'message' => 'Welcome to the Dou Dizhu API']);
    case '/create_room':
        // Handle create room request
        if ($request_method === 'POST') {
            // Generate a simple unique room ID
            $room_id = uniqid('room_');
            $rooms[$room_id] = [
                'id' => $room_id,
                'players' => [],
                'state' => 'waiting', // waiting, playing, finished
                'discarded_cards' => [], // Added discarded cards
                // Add other room-specific data here (e.g., deck, current player, turn)
            ];

            // For now, let's automatically join the creator to the room
            $player_id = uniqid('player_');
            $rooms[$room_id]['players'][$player_id] = ['id' => $player_id, 'name' => 'Creator']; // Add player details
            echo json_encode(['status' => 'success', 'message' => 'Room created and joined', 'room_id' => $room_id, 'player_id' => $player_id]);
            // Original placeholder:
            echo json_encode(['status' => 'success', 'message' => 'Room created']);
        } else {
            // Store updated rooms state
            $_SESSION['rooms'] = $rooms;
            http_response_code(405); // Method Not Allowed
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case '/join_room':
        // Handle join room request
 if ($request_method === 'POST') {
 $data = json_decode(file_get_contents('php://input'), true);
            $room_id = $data['room_id'] ?? null;
            $player_name = $data['player_name'] ?? 'Guest';

            if ($room_id && isset($rooms[$room_id])) {
                // Check if room is full (e.g., max 3 players for Dou Dizhu)
                if (count($rooms[$room_id]['players']) < 3) {
                    $player_id = uniqid('player_');
                    $rooms[$room_id]['players'][$player_id] = ['id' => $player_id, 'name' => $player_name]; // Add player details
                    echo json_encode(['status' => 'success', 'message' => 'Joined room', 'room_id' => $room_id, 'player_id' => $player_id]);
                } else {
                    http_response_code(400); // Bad Request
                    echo json_encode(['status' => 'error', 'message' => 'Room is full']);
                }
            } else {
                http_response_code(404); // Not Found
                echo json_encode(['status' => 'error', 'message' => 'Room not found']);
 }
            // Original placeholder:
             echo json_encode(['status' => 'success', 'message' => 'Joined room']);

        } else {
            // Store updated rooms state
            $_SESSION['rooms'] = $rooms;
            http_response_code(405); // Method Not Allowed
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case '/play_card':
        // Handle play card request
        if ($request_method === 'POST') {
            // Get player action and card information from request body
            // Example: $data = json_decode(file_get_contents('php://input'), true);
            // Process play card logic
            // Example: $success = play_card($data['room_id'], $data['player_id'], $data['cards']);
            // Return updated game state or error
             echo json_encode(['status' => 'success', 'message' => 'Card played']);
        } else {
            // Store updated rooms state
            $_SESSION['rooms'] = $rooms;
            http_response_code(405); // Method Not Allowed
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;

    case '/get_room_state':
        // Handle get room state request
        if ($request_method === 'GET') {
            $room_id = $_GET['room_id'] ?? null; // Get room_id from query parameter

            if ($room_id && isset($rooms[$room_id])) {
                // Return the complete room state
                echo json_encode(['status' => 'success', 'room_state' => $rooms[$room_id]]);
            } else {
                http_response_code(404); // Not Found
                echo json_encode(['status' => 'error', 'message' => 'Room not found']);
            }
        } else {
            // Store updated rooms state
            $_SESSION['rooms'] = $rooms;
            http_response_code(405); // Method Not Allowed
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
        break;
        // Store updated rooms state
        $_SESSION['rooms'] = $rooms;
    // Add more API endpoints here (e.g., /call_landlord, /pass, /game_state)

    default:
        // Handle invalid endpoint
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        break;
}

?>