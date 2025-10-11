<?php

// Get the incoming update from Telegram
$update = $GLOBALS['requestBody'];

// Log the raw update for debugging
// error_log(print_r($update, true));

// --- Message Handling ---
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';

    // --- Admin Command ---
    if ($text === '/admin') {
        // Check if the user is the administrator
        if ((string)$user_id === TELEGRAM_ADMIN_ID) {
            // Define the admin keyboard
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => ' 功能 1', 'callback_data' => 'admin_action_1'],
                        ['text' => ' 功能 2', 'callback_data' => 'admin_action_2']
                    ]
                ]
            ];

            // Send the message with the admin keyboard
            sendTelegramRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => ' 管理员菜单',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
            // If a non-admin user tries to use the /admin command, you can either ignore it
            // or send a "permission denied" message. For now, we'll just log it.
            error_log("Permission denied for /admin command from user_id: {$user_id}");
        }
    }
    // --- Hello Command (for testing) ---
    elseif ($text === '/hello') {
        sendMessage($chat_id, 'Hello there! I am up and running.');
    }

} 
// --- Callback Query Handling (for keyboard buttons) ---
elseif (isset($update['callback_query'])) {
    $callback_query = $update['callback_query'];
    $chat_id = $callback_query['message']['chat']['id'];
    $user_id = $callback_query['from']['id'];
    $callback_data = $callback_query['data'];

    // IMPORTANT: Acknowledge the callback query first to remove the "loading" state on the button
    sendTelegramRequest('answerCallbackQuery', ['callback_query_id' => $callback_query['id']]);

    // Check if the button was pressed by the administrator
    if ((string)$user_id === TELEGRAM_ADMIN_ID) {
        if ($callback_data === 'admin_action_1') {
            // Respond to "Function 1" button press
            sendMessage($chat_id, "您点击了功能1！后台逻辑在这里执行。");
        } elseif ($callback_data === 'admin_action_2') {
            // Respond to "Function 2" button press
            sendMessage($chat_id, "您点击了功能2！后台逻辑在这里执行。");
        }
    } else {
        // If a non-admin user somehow presses the button, ignore it.
        error_log("Unauthorized callback_query from user_id: {$user_id}");
    }
}

// Respond to Telegram to acknowledge receipt of the update, preventing resends.
Response::json(['status' => 'ok']);
