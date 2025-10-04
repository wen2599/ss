<?php
require_once __DIR__ . '/../init.php';

header("Content-Type: application/json");

$auth_secret = $_ENV['UPDATE_SECRET'] ?? null;

if (!$auth_secret) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Secret key not set.']);
    exit;
}

if (!isset($_POST['secret']) || $_POST['secret'] !== $auth_secret) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (isset($_POST['numbers'])) {
    $new_numbers = json_decode($_POST['numbers'], true);
    $data_file = __DIR__ . '/../data/numbers.json';

    if (json_last_error() === JSON_ERROR_NONE) {
        file_put_contents($data_file, json_encode($new_numbers, JSON_PRETTY_PRINT));
        echo json_encode(['success' => 'Numbers updated successfully.']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON format.']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No numbers data received.']);
}
?>