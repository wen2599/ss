<?php

// Version 1.1 - Added a comment to trigger deployment

require_once __DIR__ . '/vendor/autoload.php';

use App\\Lib\\LotteryParser;
use App\\Lib\\User;
use App\\Lib\\Telegram;
use App\\Lib\\Lottery;
use Monolog\\Logger;
use Monolog\\Handler\\StreamHandler;
use Dotenv\\Dotenv;

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Logger Setup
$logLevel = Logger::toMonologLevel($_ENV['LOG_LEVEL'] ?? 'INFO');
$log = new Logger('telegram_webhook');
$log->pushHandler(new StreamHandler(__DIR__ . '/app.log', $logLevel));

// Database Connection
try {
    $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], $options);
} catch (PDOException $e) {
    $log->error("Database connection failed in tg_webhook: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

// Telegram Bot Token and Admin ID from environment variables
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$adminId = $_ENV['TELEGRAM_ADMIN_ID'] ?? '';

if (empty($botToken) || empty($adminId)) {
    $log->error("Telegram BOT_TOKEN or ADMIN_ID is not set in environment variables.");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Bot configuration error.']);
    exit();
}

$telegram = new Telegram($botToken, $log);

// Get and Decode the Incoming Update
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);
$log->info("Incoming Telegram update: " . $update_json);

// 4. Process Callback Queries (Button Presses)
if (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $callback_id = $callback_query['id'];
    $callback_data = $callback_query['data'];
    $query_from_id = $callback_query['from']['id'] ?? null;

    if ($query_from_id == $adminId) {
        $parts = explode('_', $callback_data);
        $action = $parts[0];
        // Existing user approval/denial logic is commented out as per previous note.
        // It can be re-enabled or removed based on final requirements.

        // For now, just answer the callback to remove the loading state in Telegram
        $telegram->answerCallbackQuery($callback_id, '功能暂未启用或已废弃.');
    } else {
        $telegram->answerCallbackQuery($callback_id, '抱歉，您无权执行此操作。', true);
        $log->warning("Unauthorized callback query from user ID: " . $query_from_id);
    }
    http_response_code(200);
    exit();
}

// 5. Process Regular Messages
$message = null;
if (isset($update['message'])) {
    $message = $update['message'];
} elseif (isset($update['channel_post'])) {
    $message = $update['channel_post'];
}

if ($message) {
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'] ?? null;
    $text = trim($message['text'] ?? '');

    // --- Step 1: Attempt to parse any message as a lottery result first. ---
    $parsedResult = LotteryParser::parse($text);
    if ($parsedResult) {
        $statusMessage = Lottery::saveLotteryResultToDB($pdo, $parsedResult);
        if ($chat_id != $adminId) {
            // Optionally notify the admin if a public channel posts a result
            $channel_title = isset($message['chat']['title']) ? " from channel \"" . htmlspecialchars($message['chat']['title']) . "\"" : "";
            $telegram->sendMessage($adminId, "Successfully parsed a new result" . $channel_title . ":\n`" . $parsedResult['lottery_name'] . " - " . $parsedResult['issue_number'] . "`\n\nStatus: *" . $statusMessage . "*");
        } else {
            $telegram->sendMessage($chat_id, "成功识别到开奖结果：\n`" . $parsedResult['lottery_name'] . " - " . $parsedResult['issue_number'] . "`\n\n状态: *" . $statusMessage . "*");
        }
        http_response_code(200);
        exit();
    }

    // --- Step 2: If it's not a parsable result, check if the sender is the admin. ---
    if ($user_id !== (int)$adminId) {
        if ($chat_id === $user_id) { // Only respond to private chats from non-admins
            $telegram->sendMessage($chat_id, "抱歉，此机器人功能仅限管理员使用。");
        }
        $log->warning("Unauthorized message from user ID: " . $user_id . " in chat ID: " . $chat_id . " with text: " . $text);
        http_response_code(403); // Forbidden for non-admins trying to use commands
        exit();
    }

    // --- Step 3: Admin-only logic ---
    $main_menu_keyboard = ['keyboard' => [['text' => '👤 用户管理'], ['text' => '⚙️ 系统设置']], 'resize_keyboard' => true];
    $user_management_keyboard = ['keyboard' => [['text' => '📋 列出所有用户'], ['text' => '➖ 删除用户']], ['text' => '⬅️ 返回主菜单']], 'resize_keyboard' => true];
    $system_settings_keyboard = ['keyboard' => [['text' => '🔑 设定API密钥'], ['text' => 'ℹ️ 检查密钥状态']], ['text' => '⬅️ 返回主菜单']], 'resize_keyboard' => true];

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
                // Placeholder for Gemini API Key setting logic
                $telegram->sendMessage($chat_id, "此功能暂未完全实现。");
                break;
            case '/get_api_key_status':
                // Placeholder for Gemini API Key status checking logic
                $telegram->sendMessage($chat_id, "此功能暂未完全实现。");
                break;
            default:
                $telegram->sendMessage($chat_id, "抱歉，我不理解该命令。");
                break;
        }
    } else if ($chat_id == $adminId && !empty($text)) {
        // If it's the admin and not a command, try to parse as lottery result (already done above)
        // Or handle other admin-specific free text input if needed.
        $telegram->sendMessage($chat_id, "抱歉，我无法识别您的输入。请使用菜单或有效命令。");
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
