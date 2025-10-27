<?php

declare(strict_types=1);

// backend/bot.php

// --- Bootstrap aplication ---
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ai_helpers.php';
require_once __DIR__ . '/handlers.php';

// --- Configuration & Security ---
$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$secret_token = getenv('TELEGRAM_SECRET_TOKEN');
$admin_id = getenv('TELEGRAM_ADMIN_ID');
$channel_id = getenv('TELEGRAM_CHANNEL_ID');

if (!$bot_token || !$secret_token) {
    http_response_code(500); error_log("Bot token or secret token is not configured."); exit;
}

$client_secret_token = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ($client_secret_token !== $secret_token) {
    http_response_code(403); error_log("Secret Token Mismatch!"); exit;
}

// --- Main Logic ---
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) { exit; }

// 1. Handle Channel Post for lottery data
if (isset($update['channel_post'])) {
    $post = $update['channel_post'];
    $received_channel_id = $post['chat']['id'] ?? null;
    
    if ($channel_id && (string)$received_channel_id === (string)$channel_id && isset($post['text'])) {
        $parsed_data = parse_lottery_message($post['text']);
        if ($parsed_data) { save_lottery_draw($parsed_data); }
    }
    exit("OK: Channel post processed.");
}

// 2. Handle Private Message from Admin
if (isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $message_text = $update['message']['text'];

    if ($admin_id && (string)$chat_id !== (string)$admin_id) {
        send_telegram_message($chat_id, "您好！这是一个私人机器人，感谢您的关注。");
        exit("OK: Non-admin message sent.");
    }

    $user_state = get_user_state($chat_id);
    $command_parts = explode(' ', $message_text, 2);

    if ($user_state) {
        $argument = $command_parts[0] ?? '';
        switch ($user_state) {
            case 'settle': handle_settle_command($chat_id, ['/settle', $argument]); break;
            case 'report': handle_report_command($chat_id, ['/report', $argument]); break;
            case 'add': handle_add_command($chat_id, array_merge(['/add'], explode(' ', $message_text))); break;
            case 'delete': handle_delete_command($chat_id, array_merge(['/delete'], explode(' ', $message_text))); break;
            case 'find_user': handle_find_user_command($chat_id, ['/finduser', $argument]); break;
            case 'delete_user': handle_delete_user_command($chat_id, ['/deleteuser', $argument]); break;
            case 'set_gemini_key': handle_set_gemini_key_command($chat_id, ['/setgeminikey', $argument]); break;
            case 'chat_cf': handle_ai_chat_command($chat_id, $message_text, 'cloudflare'); break;
        }
        set_user_state($chat_id, null); // Clear state
    } else {
        if (strpos($message_text, '/') === 0) {
            $command = $command_parts[0];
            $argument = $command_parts[1] ?? '';

            switch ($command) {
                case '/start':
                case '/help': handle_help_command($chat_id); break;
                case '/settle': handle_settle_command($chat_id, ['/settle', $argument]); break;
                case '/report': handle_report_command($chat_id, ['/report', $argument]); break;
                case '/stats': handle_stats_command($chat_id); break;
                case '/latest': handle_latest_command($chat_id); break;
                case '/add': handle_add_command($chat_id, explode(' ', $message_text)); break;
                case '/delete': handle_delete_command($chat_id, explode(' ', $message_text)); break;
                case '/finduser': handle_find_user_command($chat_id, ['/finduser', $argument]); break;
                case '/deleteuser': handle_delete_user_command($chat_id, ['/deleteuser', $argument]); break;
                case '/setgeminikey': handle_set_gemini_key_command($chat_id, ['/setgeminikey', $argument]); break;
                case '/cfchat': handle_ai_chat_command($chat_id, $argument, 'cloudflare'); break;
                default: handle_help_command($chat_id); break;
            }
        } else {
            switch ($message_text) {
                case '结算': set_user_state($chat_id, 'settle'); send_telegram_message($chat_id, "请输入要结算的期号:"); break;
                case '结算报告': set_user_state($chat_id, 'report'); send_telegram_message($chat_id, "请输入要查看报告的期号:"); break;
                case '最新开奖': handle_latest_command($chat_id); break;
                case '系统统计': handle_stats_command($chat_id); break;
                case '帮助说明': handle_help_command($chat_id); break;
                case '查找用户': set_user_state($chat_id, 'find_user'); send_telegram_message($chat_id, "请输入要查找的用户名或邮箱:"); break;
                case '更换Gemini Key': set_user_state($chat_id, 'set_gemini_key'); send_telegram_message($chat_id, "请输入新的Gemini API Key:"); break;
                case 'CF AI 对话': set_user_state($chat_id, 'chat_cf'); send_telegram_message($chat_id, "您好，我是Cloudflare AI，请问有什么可以帮您？"); break;
                default: handle_help_command($chat_id); break;
            }
        }
    }
    exit("OK: Admin command processed.");
}

exit("OK: No actionable update received.");
