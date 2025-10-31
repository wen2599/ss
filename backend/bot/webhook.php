<?php
// backend/bot/webhook.php

// Set up error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/bot_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . '/../env_loader.php';
require_once __DIR__ . '/bot_helpers.php'; // Ensure bot_helpers is included

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

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
if (!$bot_token) {
    error_log('TELEGRAM_BOT_TOKEN is not set.');
    exit;
}

$update = json_decode($raw_input, true);

if (!$update) {
    error_log('Failed to decode JSON update.');
    exit;
}

// --- Handle Callback Queries from Inline Keyboard ---
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $callback_data = $callback_query['data'];
    $chat_id = $callback_query['message']['chat']['id'];
    $message_id = $callback_query['message']['message_id'];

    // Answer the callback query to remove the "loading" state on the button
    answer_callback_query($bot_token, $callback_query['id']);

    // Route based on callback data
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
            $help_text = "<b>🤖 机器人帮助信息</b>\n\n" .
                         "您可以点击以下按钮与我互动：\n" .
                         "- <b>查询最新开奖</b>: 获取最新的彩票开奖结果。\n" .
                         "- <b>查看我的邮箱</b>: 浏览您的最新邮件列表。\n" .
                         "\n" .
                         "如果您有其他问题或需要特定帮助，请直接发送消息给我。";
            send_message($bot_token, $chat_id, $help_text);
            break;
        default:
            // Optional: Handle unknown callback data
            send_message($bot_token, $chat_id, "未知操作。");
            break;
    }
    exit; // Stop further processing for callback queries
}

// --- Handle Regular Messages (Commands) ---
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
            // Let the user know the command is not recognized
            send_message($bot_token, $chat_id, "抱歉，我无法识别 '{$command}' 命令。");
        }
    }
}
