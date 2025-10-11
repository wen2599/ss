<?php

// This script is included by the main router, so bootstrapping is already handled.

// --- Input Processing ---
// Get the raw POST data from Telegram
$raw_input = file_get_contents('php://input');
// Decode the JSON update
$update = json_decode($raw_input, true);

// If the update is invalid or empty, there's nothing to do.
// The router will send the final 'ok' response.
if (!$update) {
    return;
}


// --- Message Processing ---
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';

    // Map clean button text to commands
    switch ($text) {
        case 'ℹ️ 欢迎信息':
            $text = '/start';
            break;
        case '⚙️ 管理员菜单':
            $text = '/admin';
            break;
        case '🤖 在线测试':
            $text = '/hello';
            break;
    }

    // --- /admin Command ---
    if ($text === '/admin') {
        // Use trim to remove any extra whitespace from the environment variable
        if (isset($user_id) && (string)$user_id === trim(TELEGRAM_ADMIN_ID)) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '功能 1', 'callback_data' => 'admin_action_1'],
                        ['text' => '功能 2', 'callback_data' => 'admin_action_2']
                    ]
                ]
            ];

            sendTelegramRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => '管理员菜单',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
             error_log("Permission denied for /admin command from user_id: {$user_id}. Expected admin_id: " . TELEGRAM_ADMIN_ID);
             sendMessage($chat_id, '您无权访问此菜单。');
        }
    }
    // --- /start Command ---
    elseif ($text === '/start') {
        $welcomeMessage = "欢迎！我是您的机器人助手。请使用下方的键盘菜单进行操作：";

        $keyboard = [
            'keyboard' => [
                [['text' => 'ℹ️ 欢迎信息']],
                [['text' => '⚙️ 管理员菜单']],
                [['text' => '🤖 在线测试']]
            ],
            'resize_keyboard' => true,
            'is_persistent' => true
        ];

        sendTelegramRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => $welcomeMessage,
            'reply_markup' => json_encode($keyboard)
        ]);
    }
    // --- /hello Command (for simple testing) ---
    elseif ($text === '/hello') {
        sendMessage($chat_id, '您好！机器人当前在线。');
    }

}
// --- Callback Query Processing (for keyboard buttons) ---
elseif (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $callback_data = $callback_query['data'];

    // Always answer the callback query first to remove the loading state on the button
    sendTelegramRequest('answerCallbackQuery', ['callback_query_id' => $callback_query['id']]);

    // Re-confirm that the user is the admin
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

// The final response to Telegram is handled by the main router (index.php)
// to prevent duplicate responses.