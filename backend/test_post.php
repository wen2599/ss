<?php
// backend/test_post.php
// This file is for testing if POST requests are allowed by the server.

header('Content-Type: application/json');

// Include CORS configuration if necessary, but for a simple test, we might skip it initially
// or include it to see if it makes a difference.
// For now, let's include it to mimic the api_router.php environment.
require_once __DIR__ . '/cors.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Try to read POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    echo json_encode([
        'success' => true,
        'method' => 'POST',
        'message' => 'POST request received successfully!',
        'received_data' => $data // Echo back the received data
    ]);
} elseif ($method === 'GET') {
    echo json_encode([
        'success' => true,
        'method' => 'GET',
        'message' => 'GET request received successfully!'
    ]);
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'method' => $method,
        'message' => 'Method Not Allowed. Only GET and POST are supported for this test.'
    ]);
}

