<?php

// This script is now included by the main router (index.php) and relies on it
// for bootstrapping, environment loading, and initial input validation.

// The router has already decoded the JSON body into $GLOBALS['requestBody']
$update = $GLOBALS['requestBody'] ?? null;

// If there's no update, it might have been a direct access attempt or an empty call.
// The router should ideally handle this, but as a safeguard:
if (!$update) {
    // Silently exit. Logging or responding might be handled by the router.
    return;
}


// --- Message Processing ---
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';

    // --- /admin Command ---
    if ($text === '/admin') {
        // Use trim to remove any extra whitespace from the environment variable
        if (isset($user_id) && (string)$user_id === trim(TELEGRAM_ADMIN_ID)) {
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Function 1', 'callback_data' => 'admin_action_1'],
                        ['text' => 'Function 2', 'callback_data' => 'admin_action_2']
                    ]
                ]
            ];

            sendTelegramRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => 'Admin Menu',
                'reply_markup' => json_encode($keyboard)
            ]);
        } else {
             error_log("Permission denied for /admin command from user_id: {$user_id}. Expected admin_id: " . TELEGRAM_ADMIN_ID);
        }
    }
    // --- /hello Command (for simple testing) ---
    elseif ($text === '/hello') {
        sendMessage($chat_id, 'Hello there! The bot is active.');
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
            sendMessage($chat_id, "You clicked Function 1! Backend logic would execute here.");
        } elseif ($callback_data === 'admin_action_2') {
            sendMessage($chat_id, "You clicked Function 2! Backend logic would execute here.");
        }
    } else {
        error_log("Unauthorized callback_query from user_id: {$user_id}");
    }
}

// The final response to Telegram is handled by the main router (index.php)
// to prevent duplicate responses.