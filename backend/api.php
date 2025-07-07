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
require_once 'game.php'; // Include the Game class
require_once 'utils.php'; // Include utility functions

header('Content-Type: application/json');

// Use a consistent way to manage rooms state, e.g., via session
$rooms = isset($_SESSION['rooms']) ? $_SESSION['rooms'] : [];

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

                    // If the room is now full, start the game
                    if (count($rooms[$room_id]['players']) === 3) {
                        // Get players data in the format required by the Game class
                        $gamePlayers = [];
                        foreach ($rooms[$room_id]['players'] as $p) {
                            $gamePlayers[$p['id']] = $p; // Use player ID as key
                        }

                        $game = new Game($room_id, $gamePlayers);
                        $game->startGame(); // This deals the cards and sets initial state

                        // Update the room state in the $rooms array
                        $rooms[$room_id] = array_merge($rooms[$room_id], $game->getGameState());
                        $rooms[$room_id]['state'] = 'playing'; // Set room state to playing
                    }

                    echo json_encode(['status' => 'success', 'message' => 'Joined room', 'room_id' => $room_id, 'player_id' => $player_id]);
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
                $roomState = $rooms[$room_id];

                // IMPORTANT: Filter player hands for privacy
                // Only send the current player's hand, hide other players' hands
                if (isset($_GET['player_id'])) {
                     $requestingPlayerId = $_GET['player_id'];
                     foreach ($roomState['players'] as $playerId => &$playerData) {
                         if ($playerId !== $requestingPlayerId) {
                             unset($playerData['hand']); // Remove hand data for other players
                         }
                     }
                }
                echo json_encode(['status' => 'success', 'room_state' => $roomState]);
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
    // Add more API endpoints here (e.g., /call_landlord, /pass, /game_state)

    default:
        // Handle invalid endpoint
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        break;
}

?>