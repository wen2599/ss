<?php

use App\\Lib\\LotteryParser;
use App\\Lib\\User;
use App\\Lib\\Telegram;
use App\\Lib\\Lottery;
use Monolog\\Logger;
use Monolog\\Handler\\StreamHandler;

// Centralized initialization
require_once __DIR__ . '/init.php';
// Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// --- Logger Setup ---
// The logger is now set up similarly to index.php for consistency.
$logLevel = Logger::toMonologLevel($_ENV['LOG_LEVEL'] ?? 'INFO');
$log = new Logger('telegram_webhook');
$log->pushHandler(new StreamHandler(__DIR__ . '/app.log', $logLevel));

try {
    // --- Configuration ---
    $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
    $adminId = $_ENV['TELEGRAM_ADMIN_ID'] ?? '';

    if (empty($botToken) || empty($adminId)) {
        throw new Exception("Telegram BOT_TOKEN or ADMIN_ID is not set in environment variables.", 500);
    }

    $telegram = new Telegram($botToken, $log);

    // --- Process Incoming Update ---
    $update_json = file_get_contents('php://input');
    if (empty($update_json)) {
        throw new Exception("No input received.", 200); // Changed to 200 as it might be an empty POST from TG, not necessarily an error
    }
    $update = json_decode($update_json, true);
    $log->info("Incoming Telegram update: " . $update_json);
    
    // --- Callback Query Processing ---
    if (isset($update['callback_query'])) {
        $callback_query = $update['callback_query'];
        $callback_id = $callback_query['id'];
        $query_from_id = $callback_query['from']['id'] ?? null;

        if ($query_from_id == $adminId) {
            $telegram->answerCallbackQuery($callback_id, '功能暂未启用或已废弃.');
        } else {
            $telegram->answerCallbackQuery($callback_id, '抱歉，您无权执行此操作。', true);
            $log->warning("Unauthorized callback query from user ID: " . $query_from_id);
        }
        // Respond and exit cleanly for callbacks.
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Callback processed.']);
        exit();
    }
    
    // --- Message Processing ---
    $message = $update['message'] ?? $update['channel_post'] ?? null;

    if (!$message) {
        throw new Exception("No valid message or channel post found in the update.", 200); // 200 OK as we've received the webhook, just nothing to do.
    }
    
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'] ?? null;
    $text = trim($message['text'] ?? '');

    // 1. Attempt to parse as a lottery result first (from any source)
    $parsedResult = LotteryParser::parse($text);
    if ($parsedResult) {
        $statusMessage = Lottery::saveLotteryResultToDB($pdo, $parsedResult);
        $log->info("Parsed lottery result from chat_id: $chat_id. Status: $statusMessage");

        if ($chat_id != $adminId) {
            $telegram->sendMessage($adminId, "A new result was parsed from a channel/group:\n`" . $parsedResult['lottery_name'] . "`\nStatus: *" . $statusMessage . "*");
        } else {
            $telegram->sendMessage($chat_id, "Result parsed successfully.\nStatus: *" . $statusMessage . "*");
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Lottery result processed.']);
        exit();
    }

    // 2. Admin-only command processing
    if ($user_id != (int)$adminId) {
        if ($chat_id === $user_id) { // Private chat with a non-admin
            $telegram->sendMessage($chat_id, "抱歉，此机器人功能仅限管理员使用。");
        }
        $log->warning("Unauthorized message from user_id: $user_id in chat_id: $chat_id");
        throw new Exception("User is not authorized.", 403);
    }

    // --- Admin-only logic ---
    $main_menu_keyboard = ['keyboard' => [['text' => '👤 用户管理'], ['text' => '⚙️ 系统设置']], 'resize_keyboard' => true];
    $user_management_keyboard = ['keyboard' => [['text' => '📋 列出所有用户'], ['text' => '➖ 删除用户'], ['text' => '⬅️ 返回主菜单']], 'resize_keyboard' => true];
    $system_settings_keyboard = ['keyboard' => [['text' => '🔑 设定API密钥'], ['text' => 'ℹ️ 检查密钥状态'], ['text' => '⬅️ 返回主菜单']], 'resize_keyboard' => true];

    $command_map = [
        '👤 用户管理' => '/user_management',
        '⚙️ 系统设置' => '/system_settings',
        '➖ 删除用户' => '/deluser',
        '📋 列出所有用户' => '/listusers',
        '🔑 设定API密钥' => '/set_gemini_key',
        'ℹ️ 检查密钥状态' => '/get_api_key_status',
        '⬅️ 返回主菜单' => '/start',
    ];

    $command = null;
    $args = '';

    if (isset($command_map[$text])) {
        $command = $command_map[$text];
    } else if (strpos($text, '/') === 0) {
        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $args = $parts[1] ?? '';
    }

    if ($command) {
        switch ($command) {
            case '/start':
                $telegram->sendMessage($chat_id, "您好，管理员！请从菜单中选择一个操作：", $main_menu_keyboard);
                break;
            case '/user_management':
                $telegram->sendMessage($chat_id, "👤 *用户管理*\n\n请选择一个操作：", $user_management_keyboard);
                break;
            case '/system_settings':
                $telegram->sendMessage($chat_id, "⚙️ *系统设置*\n\n请选择一个操作：", $system_settings_keyboard);
                break;
            case '/deluser':
                $responseText = !empty($args) ? User::deleteUserFromDB($pdo, $args) : "用法：`/deluser <telegram_id>`";
                $telegram->sendMessage($chat_id, $responseText);
                break;
            case '/listusers':
                $telegram->sendMessage($chat_id, User::listUsersFromDB($pdo));
                break;
            case '/set_gemini_key':
                $telegram->sendMessage($chat_id, "此功能暂未完全实现。");
                break;
            case '/get_api_key_status':
                $telegram->sendMessage($chat_id, "此功能暂未完全实现。");
                break;
            default:
                $telegram->sendMessage($chat_id, "抱歉，我不理解该命令。");
                break;
        }
    } else if ($chat_id == $adminId && !empty($text)) {
        $telegram->sendMessage($chat_id, "抱歉，我无法识别您的输入。请使用菜单或有效命令。");
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Admin command processed or no action taken.']);

} catch (Exception $e) {
    $log->error("Error in tg_webhook.php: " . $e->getMessage(), ['exception' => $e]);
    $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

?>