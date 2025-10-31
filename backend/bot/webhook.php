<?php
// backend/bot/webhook.php

// --- SETUP AND INITIALIZATION ---
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_errors.log');
error_reporting(E_ALL);

// Load environment variables and helper functions
require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/bot_helpers.php';
require_once __DIR__ . '/lottery_processor.php'; // <-- INCLUDE THE NEW PROCESSOR

// --- CAPTURE AND RESPOND TO TELEGRAM ---
// Capture raw input from Telegram
$raw_input = file_get_contents('php://input');
file_put_contents(__DIR__ . '/bot_updates.log', $raw_input . "\n", FILE_APPEND);

// Immediately send a 200 OK to Telegram to prevent timeouts
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    header("Content-Length: 0");
    header("Connection: close");
    flush();
}

// --- PROCESS THE INCOMING UPDATE ---
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (!$bot_token) {
    error_log('CRITICAL: TELEGRAM_BOT_TOKEN is not set. Halting execution.');
    exit;
}

$update = json_decode($raw_input, true);
if (!$update) {
    error_log('Failed to decode JSON update.');
    exit;
}

// --- ROUTING LOGIC ---

// ** NEW: Handle Channel Posts (for lottery results) **
if (isset($update['channel_post'])) {
    $channel_post = $update['channel_post'];
    $post_text = isset($channel_post['text']) ? $channel_post['text'] : '';
    if (!empty($post_text)) {
        // Hand off the text to our specialized processor
        process_lottery_result($post_text);
    }
    exit; // Stop further processing for channel posts
}

// --- Handle Callback Queries (from inline keyboards) ---
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $callback_data = $callback_query['data'];
    $chat_id = $callback_query['message']['chat']['id'];
    
    answer_callback_query($bot_token, $callback_query['id']);

    switch ($callback_data) {
        case 'lottery_latest':
            require_once __DIR__ . '/commands/lottery_latest.php';
            handle_callback($bot_token, $chat_id, $callback_data);
            break;
        case 'view_emails':
            require_once __DIR__ . '/commands/view_emails.php';
            handle_callback($bot_token, $chat_id, $callback_data);
            break;
        case 'help':
            $help_text = "<b>🤖 机器人帮助信息</b>\n\n点击下方按钮进行操作：";
            send_message($bot_token, $chat_id, $help_text);
            break;
        default:
            send_message($bot_token, $chat_id, "未知操作。");
            break;
    }
    exit;
}

// --- Handle Regular Messages (user commands) ---
if (isset($update['message'])) {
    $message = $update['message'];
    $text = isset($message['text']) ? $message['text'] : '';
    $chat_id = isset($message['chat']['id']) ? $message['chat']['id'] : null;

    if (!$chat_id) {
        error_log('Could not determine chat_id from message.');
        exit;
    }

    if (preg_match('/^\/(\w+)/', $text, $matches)) {
        $command = $matches[1];
        $command_file = __DIR__ . "/commands/{$command}.php";

        if (file_exists($command_file)) {
            require_once $command_file;
            handle_command($bot_token, $chat_id, $message);
        } else {
            send_message($bot_token, $chat_id, "抱歉，无法识别该命令: /{$command}");
        }
    }
    // Note: Non-command messages are currently ignored. You can add logic here if needed.
}
