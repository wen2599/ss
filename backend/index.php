<?php
// backend/index.php

// --- CORS Whitelist ---
$allowed_origins = [
    'https://ss.wenxiuxiu.eu.org',
    'http://localhost:5173'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    // Optionally, you could fall back to a default or deny the request.
    // For this case, we'll default to the primary production origin.
    header("Access-Control-Allow-Origin: https://ss.wenxiuxiu.eu.org");
}

// --- Universal Headers ---
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


// --- Handle preflight OPTIONS request ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Autoload & Includes ---
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/src/controllers/EmailController.php';
require_once __DIR__ . '/src/controllers/UserController.php';

// --- Error Handling & DB Connection ---
try {
    $conn = get_db_connection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// --- Routing Logic ---
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$trimmed_path = trim($path, '/');
$path_parts = explode('/', $trimmed_path);
$resource = array_shift($path_parts) ?? ''; // e.g., 'emails', 'users', 'numbers'

// --- Instantiate Controllers ---
$emailController = new EmailController($conn);
$userController = new UserController($conn);

// --- Route Definitions ---
switch ($resource) {
    case 'emails':
        if ($request_method === 'GET') {
            $id = $path_parts[0] ?? null;
            $emailController->handleGetEmails($id);
        } elseif ($request_method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $emailController->handlePostEmail($data);
        }
        break;

    case 'numbers':
        if ($request_method === 'GET') {
            $userController->handleGetNumbers();
        }
        break;

    case 'register':
        if ($request_method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $userController->handleRegisterUser($data);
        }
        break;

    default:
        // Default route (optional, can serve numbers or a welcome message)
        if ($request_method === 'GET') {
            $userController->handleGetNumbers();
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Endpoint Not Found']);
        }
        break;
}

// --- Close Connection ---
$conn->close();
?>