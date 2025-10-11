<?php

// 唯一的入口点，加载所有依赖
require_once __DIR__ . '/../config.php';

// 从 php://input 获取原始请求体
$raw_input = file_get_contents('php://input');
if (!$raw_input) {
    // 如果没有输入，则不执行任何操作。这可以防止直接浏览器访问产生错误。
    Response::json(['status' => 'ok', 'message' => 'No input received.']);
    exit;
}

// 解码来自Telegram的JSON更新
$update = json_decode($raw_input, true);

// 如果JSON解码失败，记录错误并退出
if (!$update) {
    error_log('Telegram Webhook Error: Failed to decode JSON. Raw input: ' . $raw_input);
    Response::json(['status' => 'error', 'message' => 'Invalid JSON received.']);
    exit;
}

// 将更新存储在全局变量中，以便后续脚本访问
$GLOBALS['requestBody'] = $update;

// --- 消息处理 ---
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';

    // --- /admin 命令处理 ---
    if ($text === '/admin') {
        // 使用 trim 确保环境变量中可能存在的多余空格被去除
        if (isset($user_id) && (string)$user_id === trim(TELEGRAM_ADMIN_ID)) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => ' 功能 1', 'callback_data' => 'admin_action_1'],
                        ['text' => ' 功能 2', 'callback_data' => 'admin_action_2']
                    ]
                ]
            ];

            sendTelegramRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => ' 管理员菜单',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
             error_log("Permission denied for /admin command from user_id: {$user_id}. Expected admin_id: " . TELEGRAM_ADMIN_ID);
        }
    }
    // --- /hello 命令 (用于简单测试) ---
    elseif ($text === '/hello') {
        sendMessage($chat_id, 'Hello there! The bot is active.');
    }

}
// --- 回调查询处理 (用于键盘按钮) ---
elseif (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $callback_data = $callback_query['data'];

    // 总是先回应callback query，消除按钮上的加载状态
    sendTelegramRequest('answerCallbackQuery', ['callback_query_id' => $callback_query['id']]);

    // 再次确认是管理员在操作
    if (isset($user_id) && (string)$user_id === trim(TELEGRAM_ADMIN_ID)) {
        if ($callback_data === 'admin_action_1') {
            sendMessage($chat_id, "您点击了功能1！后台逻辑在这里执行。");
        } elseif ($callback_data === 'admin_action_2') {
            sendMessage($chat_id, "您点击了功能2！后台逻辑在这里执行。");
        }
    } else {
        error_log("Unauthorized callback_query from user_id: {$user_id}");
    }
}

// 最后，向Telegram确认已收到更新，防止它重复发送
Response::json(['status' => 'ok']);