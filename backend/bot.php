<?php

declare(strict_types=1);

// backend/bot.php

// --- Enhanced Debugging ---
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/debug.log');

// Polyfill for getallheaders() if it doesn't exist (e.g., in FPM-CGI).
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

function log_debug($message) {
    $timestamp = date("Y-m-d H:i:s");
    $log_entry = "[{$timestamp}] " . $message . "\n";
    file_put_contents(__DIR__ . '/debug.log', $log_entry, FILE_APPEND);
}

log_debug("--- Webhook Triggered ---");
log_debug("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
log_debug("Raw Input: " . file_get_contents('php://input'));
log_debug("Headers: " . json_encode(getallheaders()));
// --- End Debugging ---


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
    $message_text = trim($update['message']['text']); // Trim message text

    if ($admin_id && (string)$chat_id !== (string)$admin_id) {
        send_telegram_message($chat_id, "您好！这是一个私人机器人，感谢你关注。");
        exit("OK: Non-admin message sent.");
    }

    $user_state = get_user_state($chat_id);
    $command_parts = explode(' ', $message_text, 2);
    $first_word = strtolower($command_parts[0]);

    if ($user_state) {
        $argument = $message_text; // For state-based commands, the whole message is the argument

        // Check for an exit command first.
        if (in_array($argument, ['/done', '退出', '退出会话'])) {
            set_user_state($chat_id, null); // Clear state
            handle_help_command($chat_id); // Show main menu
            exit("OK: User exited session.");
        }

        switch ($user_state) {
            case 'settle':
                handle_settle_command($chat_id, ['/settle', $argument]);
                set_user_state($chat_id, null); // Clear state after non-chat command
                break;
            case 'report':
                handle_report_command($chat_id, ['/report', $argument]);
                set_user_state($chat_id, null);
                break;
            case 'add':
                handle_add_command($chat_id, array_merge(['/add'], explode(' ', $argument)));
                set_user_state($chat_id, null);
                break;
            case 'delete':
                handle_delete_command($chat_id, array_merge(['/delete'], explode(' ', $argument)));
                set_user_state($chat_id, null);
                break;
            case 'find_user':
                handle_find_user_command($chat_id, ['/finduser', $argument]);
                set_user_state($chat_id, null);
                break;
            case 'delete_user':
                handle_delete_user_command($chat_id, ['/deleteuser', $argument]);
                set_user_state($chat_id, null);
                break;
            case 'set_gemini_key':
                handle_set_gemini_key_command($chat_id, ['/setgeminikey', $argument]);
                set_user_state($chat_id, null);
                break;
            // For AI chats, we do NOT clear the state, allowing for a continuous conversation.
            case 'chat_cf':
                handle_ai_chat_command($chat_id, $argument, 'cloudflare');
                break;
            case 'chat_gemini':
                handle_ai_chat_command($chat_id, $argument, 'gemini');
                break;
        }
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
                case '/geminichat': handle_ai_chat_command($chat_id, $argument, 'gemini'); break; // Direct Gemini chat command
                default: handle_help_command($chat_id); break;
            }
        } else {
            // Keyboard button presses
            switch ($message_text) {
                case '结算': set_user_state($chat_id, 'settle'); send_telegram_message($chat_id, "请输入要结算的期号:"); break;
                case '结算报告': set_user_state($chat_id, 'report'); send_telegram_message($chat_id, "请输入要查看报告的期号:"); break;
                case '最新开奖': handle_latest_command($chat_id); break;
                case '系统统计': handle_stats_command($chat_id); break;
                case '帮助说明': handle_help_command($chat_id); break;
                case '查找用户': set_user_state($chat_id, 'find_user'); send_telegram_message($chat_id, "请输入要查找的用户名或邮箱:"); break;
                case '删除用户': set_user_state($chat_id, 'delete_user'); send_telegram_message($chat_id, "⚠️ 警告！此操作将永久删除用户及其所有数据！\n请输入要删除的用户的用户名或邮箱:"); break;
                case '手动添加': set_user_state($chat_id, 'add'); send_telegram_message($chat_id, "请输入记录 (类型 期号 号码):\n例如:\n香港六合彩 2023001 01,02,03,04,05,06,07"); break;
                case '删除记录': set_user_state($chat_id, 'delete'); send_telegram_message($chat_id, "请输入记录 (类型 期号):\n例如:\n香港六合彩 2023001"); break;
                case '更换Gemini Key': set_user_state($chat_id, 'set_gemini_key'); send_telegram_message($chat_id, "请输入新的Gemini API Key:\n(请确保您的API密钥是正确的，通常以'AIza'开头)"); break;
                case 'CF AI 对话': set_user_state($chat_id, 'chat_cf'); send_telegram_message($chat_id, "您好，我是Cloudflare AI，请问有什么可以帮您？"); break;
                case 'Gemini AI 对话': set_user_state($chat_id, 'chat_gemini'); send_telegram_message($chat_id, "您好，我是Gemini Pro，请问有什么可以帮您？"); break;
                default: handle_help_command($chat_id); break;
            }
        }
    }
    exit("OK: Admin command processed.");
}

exit("OK: No actionable update received.");
