<?php
// backend/api/tg_webhook.php
// SIMPLIFIED DEBUGGING VERSION

// Only require the config file to get the necessary constants.
require_once __DIR__ . '/config.php';

// A simplified sendMessage function without logging, to reduce failure points.
function sendMessage(int $chat_id, string $text) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $post_fields = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    @curl_exec($ch);
    curl_close($ch);
}

// --- Main Execution Block ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'No update data received.']);
    exit();
}

// Respond immediately to the webhook to avoid timeouts
http_response_code(200);
echo json_encode(['status' => 'ok']);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Check for an admin command
if (isset($update['message']) && isset($update['message']['text']) && $update['message']['text'][0] === '/') {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];

    // The core test:
    if ($user_id == TELEGRAM_SUPER_ADMIN_ID) {
        sendMessage($chat_id, "DEBUG SUCCESS: Admin ID match confirmed.");
    } else {
        // Provide detailed feedback if the ID does not match
        $received_id_type = gettype($user_id);
        $env_id_type = gettype(TELEGRAM_SUPER_ADMIN_ID);
        $error_msg = "DEBUG FAILURE: Admin ID mismatch.\nReceived ID: {$user_id} (Type: {$received_id_type})\nExpected ID: " . TELEGRAM_SUPER_ADMIN_ID . " (Type: {$env_id_type})";
        sendMessage($chat_id, $error_msg);
    }
}
?>
