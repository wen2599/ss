<?php

declare(strict_types=1);

// backend/bot.php

// --- Bootstrap aplication ---
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/handlers.php';

// --- Configuration & Security ---
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$secret_token = getenv('TELEGRAM_SECRET_TOKEN');
$admin_id = getenv('TELEGRAM_ADMIN_ID');
$channel_id = getenv('TELEGRAM_CHANNEL_ID');

if (! $bot_token || ! $secret_token) {
    http_response_code(500);
    error_log("Bot token or secret token is not configured.");
    exit("Bot token or secret token is not configured.");
}

$client_secret_token = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($client_secret_token !== $secret_token) {
    http_response_code(403);
    error_log("Secret Token Mismatch! Expected: '{$secret_token}', but got: '{$client_secret_token}'. Check your webhook settings.");
    exit("Forbidden: Secret token mismatch.");
}

// --- Main Logic ---
$raw_input = file_get_contents('php://input');
$update = json_decode($raw_input, true);

if (! $update) {
    http_response_code(200);
    exit("OK: No valid update received.");
}

// 1. Handle Channel Post
if (isset($update['channel_post'])) {
    $post = $update['channel_post'];
    // Check if it's a post from the configured channel and contains text
    if (! empty($channel_id) && $post['chat']['id'] == $channel_id && isset($post['text'])) {
        $parsed_data = parse_lottery_message($post['text']);
        if ($parsed_data) {
            save_lottery_draw($parsed_data);
        }
    } elseif (! empty($post['chat']['id'])) {
        // Log if a post is received from a different channel
        error_log("Received channel post from an unsubscribed or incorrect channel ID: " . $post['chat']['id']);
    }
    http_response_code(200);
    exit("OK: Channel post processed.");
}

// 2. Handle Private Message
if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $message_text = $update['message']['text'];

    // Restrict access to the admin user
    if (! empty($admin_id) && $chat_id != $admin_id) {
        send_telegram_message($chat_id, "您好！这是一个私人机器人，感谢您的关注。");
        http_response_code(200);
        exit("OK: Message sent to non-admin.");
    }

    // --- Define Keyboard Menu ---
    $keyboard = [
        'keyboard' => [
            ['/latest', '/stats'],
            ['/help']
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
        'persistent' => true
    ];
    $reply_markup = json_encode($keyboard);

    // Process commands
    $command_parts = explode(' ', $message_text, 3);
    $command = $command_parts[0];

    switch ($command) {
        case '/start':
        case '/help':
            handle_help_command($chat_id, $reply_markup);
            break;
        case '/stats':
            handle_stats_command($chat_id);
            break;
        case '/latest':
            handle_latest_command($chat_id);
            break;
        case '/add':
            handle_add_command($chat_id, $command_parts);
            break;
        case '/delete':
            handle_delete_command($chat_id, $command_parts);
            break;
        default:
            send_telegram_message($chat_id, "未知命令: {$command}。请使用键盘或输入 /help 查看可用命令。", $reply_markup);
            break;
    }

    http_response_code(200);
    exit("OK: Admin command processed.");
}

// 3. Handle other update types
http_response_code(200);
exit("OK: Unhandled update type received.");
