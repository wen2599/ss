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
    // --- /start Command ---
    elseif ($text === '/start') {
        $welcomeMessage = "Welcome! I am your bot assistant. Here are the available commands:\n\n" .
                          "/start - Show this welcome message\n" .
                          "/admin - Access the admin menu\n" .
                          "/hello - Check if the bot is active";
        sendMessage($chat_id, $welcomeMessage);
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