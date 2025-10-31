<?php
// backend/bot/webhook.php

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/../db_connection.php';

// Log the raw input from Telegram
$raw_input = file_get_contents('php://input');
file_put_contents(__DIR__ . '/bot_updates.log', $raw_input . "\n", FILE_APPEND);

// Immediately send a 200 OK response to Telegram
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    header("Content-Length: 0");
    header("Connection: close");
    flush();
}

$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
if (!$bot_token) {
    error_log('TELEGRAM_BOT_TOKEN is not set.');
    exit;
}

$update = json_decode($raw_input, true);

if (!$update) {
    error_log('Failed to decode JSON update.');
    exit;
}

if (isset($update['message'])) {
    $message = $update['message'];
    $text = $message['text'] ?? '';
    $chat_id = $message['chat']['id'] ?? null;

    if (!$chat_id) {
        error_log('Could not determine chat_id.');
        exit;
    }

    if (preg_match('/^\/(\w+)/', $text, $matches)) {
        $command = $matches[1];
        $command_file = __DIR__ . "/commands/{$command}.php";

        if (file_exists($command_file)) {
            try {
                require_once $command_file;
                handle_command($bot_token, $chat_id, $message);
            } catch (Exception $e) {
                error_log("Error in command {$command}: " . $e->getMessage());
            }
        } else {
            error_log("Command file not found: {$command_file}");
        }
    }
}
