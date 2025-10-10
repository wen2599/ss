<?php

// API handler to provide the latest lottery number to the frontend

require_once __DIR__ . '/../core/Response.php';

// Path to the stored data
$storagePath = __DIR__ . '/../../data/lottery_latest.json';

if (file_exists($storagePath)) {
    $content = file_get_contents($storagePath);
    $data = json_decode($content, true);

    // Check if json is valid
    if (json_last_error() === JSON_ERROR_NONE) {
        Response::json($data);
    } else {
        Response::json(['error' => 'Data file is corrupted'], 500);
    }
} else {
    // If the file doesn't exist yet, return a placeholder
    Response::json([
        'lottery_number' => 'Waiting for first number...',
        'received_at_utc' => null
    ], 404);
}
