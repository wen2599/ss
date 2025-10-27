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

error_log("Bot Start: Received webhook request.");
error_log("Config: TELEGRAM_ADMIN_ID=" . (empty($admin_id) ? "NOT_SET" : $admin_id));
error_log("Config: TELEGRAM_CHANNEL_ID=" . (empty($channel_id) ? "NOT_SET" : $channel_id));

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

error_log("Raw Telegram Update: " . $raw_input);

if (! $update) {
    http_response_code(200);
    exit("OK: No valid update received.");
}

// 1. Handle Channel Post
if (isset($update['channel_post'])) {
    $post = $update['channel_post'];
    $received_channel_id = $post['chat']['id'] ?? 'UNKNOWN';
    $post_text = $post['text'] ?? 'NO_TEXT';

    error_log("Received Channel Post from ID: {$received_channel_id}");
    error_log("Channel Post Text: {$post_text}");

    // Check if it's a post from the configured channel and contains text
    if (! empty($channel_id) && (string)$received_channel_id === (string)$channel_id && isset($post['text'])) {
        error_log("Channel ID matched. Attempting to parse lottery message.");
        $parsed_data = parse_lottery_message($post['text']);
        if ($parsed_data) {
            error_log("Lottery message parsed successfully. Data: " . json_encode($parsed_data));
            if (save_lottery_draw($parsed_data)) {
                error_log("Lottery draw saved successfully.");
            } else {
                error_log("Failed to save lottery draw.");
            }
        } else {
            error_log("Failed to parse lottery message from channel post.");
        }
    } elseif (! empty($received_channel_id)) {
        error_log("Received channel post from an unsubscribed or incorrect channel ID: {$received_channel_id}. Expected: {$channel_id}");
    }
    http_response_code(200);
    exit("OK: Channel post processed.");
}

// 2. Handle Private Message
if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $message_text = $update['message']['text'];

    error_log("Received Private Message from Chat ID: {$chat_id}");
    error_log("Private Message Text: {$message_text}");

    // Restrict access to the admin user
    if (! empty($admin_id) && (string)$chat_id !== (string)$admin_id) {
        send_telegram_message($chat_id, "您好！这是一个私人机器人，感谢您的关注。");
        error_log("Blocked non-admin message from chat ID: {$chat_id}");
        http_response_code(200);
        exit("OK: Message sent to non-admin.");
    }

    // Check for a pending command state
    $user_state = get_user_state($chat_id);

    if ($user_state) {
        error_log("User {$chat_id} is in state: {$user_state}");
        $args = explode(' ', $message_text);
        $command_parts = array_merge(['/' . $user_state], $args);

        switch($user_state) {
            case 'add':
                handle_add_command($chat_id, $command_parts);
                break;
            case 'delete':
                handle_delete_command($chat_id, $command_parts);
                break;
            case 'find_user':
                $command_parts = array_merge(['/finduser'], $args);
                handle_find_user_command($chat_id, $command_parts);
                break;
            case 'delete_user':
                $command_parts = array_merge(['/deleteuser'], $args);
                handle_delete_user_command($chat_id, $command_parts);
                break;
        }

        set_user_state($chat_id, null); // Clear state after handling
    } else {
        // Handle as a new command or keyboard press
        if (strpos($message_text, '/') === 0) {
            $command_parts = explode(' ', $message_text);
            $command = $command_parts[0];
            error_log("Admin command received: {$command}");

            switch ($command) {
                case '/start':
                case '/help':
                    handle_help_command($chat_id);
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
                case '/finduser':
                    handle_find_user_command($chat_id, $command_parts);
                    break;
                case '/deleteuser':
                    handle_delete_user_command($chat_id, $command_parts);
                    break;
                default:
                    handle_help_command($chat_id);
                    break;
            }
        } else {
            // Handle keyboard button presses (Chinese text)
            error_log("Admin keyboard button press received: {$message_text}");
            switch ($message_text) {
                case '最新开奖':
                    handle_latest_command($chat_id);
                    break;
                case '系统统计':
                    handle_stats_command($chat_id);
                    break;
                case '手动添加':
                    set_user_state($chat_id, 'add');
                    send_telegram_message($chat_id, "请输入要添加的记录，格式为:\n[类型] [期号] [号码]\n\n例如:\n香港六合彩 2023001 01,02,03,04,05,06,07");
                    break;
                case '删除记录':
                    set_user_state($chat_id, 'delete');
                    send_telegram_message($chat_id, "请输入要删除的记录，格式为:\n[类型] [期号]\n\n例如:\n香港六合彩 2023001");
                    break;
                case '查找用户':
                    set_user_state($chat_id, 'find_user');
                    send_telegram_message($chat_id, "请输入要查找的用户的用户名或邮箱:");
                    break;
                case '删除用户':
                    set_user_state($chat_id, 'delete_user');
                    send_telegram_message($chat_id, "⚠️ 警告：此操作将永久删除用户及其所有数据！\n请输入要删除的用户的用户名或邮箱:");
                    break;
                case '帮助说明':
                    handle_help_command($chat_id);
                    break;
                default:
                    handle_help_command($chat_id);
                    break;
            }
        }
    }
    http_response_code(200);
    exit("OK: Admin command processed.");
}

// 3. Handle other update types
error_log("Unhandled update type received.");
http_response_code(200);
exit("OK: Unhandled update type received.");
