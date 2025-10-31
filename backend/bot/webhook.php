<?php
// backend/bot/webhook.php

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';

$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'];

// Immediately send a 200 OK response to Telegram
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    header("Content-Length: 0");
    header("Connection: close");
    flush();
}

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $text = $message['text'];
    $chat_id = $message['chat']['id'];

    if (preg_match('/^/(\w+)/', $text, $matches)) {
        $command = $matches[1];
        $command_file = __DIR__ . "/commands/{$command}.php";

        if (file_exists($command_file)) {
            require_once $command_file;
            handle_command($bot_token, $chat_id, $message);
        }
    }
}
