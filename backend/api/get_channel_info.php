<?php
// backend/api/get_channel_info.php
require_once 'config.php';
header('Content-Type: application/json');

// Check if the Telegram constants are defined and not the placeholder values
if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHANNEL_ID') || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN' || TELEGRAM_CHANNEL_ID === 'YOUR_CHANNEL_ID') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Telegram Bot Token or Channel ID is not configured on the server.']);
    exit;
}

$token = TELEGRAM_BOT_TOKEN;
$channel_id = TELEGRAM_CHANNEL_ID;

$api_url = "https://api.telegram.org/bot{$token}/getChat?chat_id={$channel_id}";

// Use file_get_contents to make the API request.
// The '@' suppresses warnings on failure, which we handle manually.
$response = @file_get_contents($api_url);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to connect to the Telegram API.']);
    exit;
}

$data = json_decode($response, true);

if (!$data || !$data['ok']) {
    http_response_code(500);
    $error_message = $data['description'] ?? 'An unknown error occurred with the Telegram API.';
    echo json_encode(['success' => false, 'message' => "Telegram API Error: " . $error_message]);
    exit;
}

// Extract the relevant information from the 'result' object
$channel_info = $data['result'];

echo json_encode(['success' => true, 'data' => [
    'title' => $channel_info['title'] ?? null,
    'description' => $channel_info['description'] ?? null,
    'invite_link' => $channel_info['invite_link'] ?? null,
    // Note: members_count is only returned for supergroups and channels.
    'members_count' => $channel_info['members_count'] ?? null,
]]);
?>
