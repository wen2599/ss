<?php

/**
 * Sends a generic request to the Telegram Bot API.
 *
 * @param string $method The API method to call (e.g., 'sendMessage').
 * @param array  $data   The data to send with the request.
 * @return array|false The JSON-decoded response from the API, or false on failure.
 */
function sendTelegramRequest($method, $data) {
    // This function now relies on getenv(), which is populated by config.php
    $token = getenv('TELEGRAM_BOT_TOKEN');
    if (empty($token) || $token === 'your_telegram_bot_token_here') {
        error_log("CRITICAL: Telegram request failed because TELEGRAM_BOT_TOKEN is not configured.");
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/{$method}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10-second timeout

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Telegram API error for method {$method}: HTTP {$http_code} - {$response} - cURL Error: {$error}");
        return false;
    }

    return json_decode($response, true);
}


/**
 * Sends a message to a specific Telegram chat.
 *
 * @param int        $chatId      The ID of the chat to send the message to.
 * @param string     $text        The message text.
 * @param array|null $replyMarkup Optional. A keyboard markup object.
 * @return bool True on success, false on failure.
 */
function sendTelegramMessage($chatId, $text, $replyMarkup = null) {
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];

    if ($replyMarkup) {
        // The reply_markup must be a JSON string.
        $payload['reply_markup'] = json_encode($replyMarkup);
    }

    return sendTelegramRequest('sendMessage', $payload) !== false;
}

/**
 * Answers a callback query (e.g., after a button press).
 * This stops the loading animation on the button.
 *
 * @param string $callbackQueryId The ID of the callback query.
 * @return bool True on success, false on failure.
 */
function answerTelegramCallbackQuery($callbackQueryId) {
    if (empty($callbackQueryId)) return false;
    $payload = ['callback_query_id' => $callbackQueryId];
    return sendTelegramRequest('answerCallbackQuery', $payload) !== false;
}


/**
 * Generates the main admin keyboard with all top-level functions.
 *
 * @return array The keyboard markup.
 */
function getAdminKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '👤 用户管理', 'callback_data' => 'menu_user_management'], ['text' => '📁 文件管理', 'callback_data' => 'menu_file_management']],
            [['text' => '🧠 请求 Gemini', 'callback_data' => 'ask_gemini'], ['text' => '☁️ 请求 Cloudflare', 'callback_data' => 'ask_cloudflare']],
            [['text' => '🔑 更换 API 密钥', 'callback_data' => 'menu_api_keys']],
        ]
    ];
}

/**
 * Generates a keyboard for the file management menu.
 *
 * @return array The keyboard markup.
 */
function getFileManagementKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '👁️ 列出文件', 'callback_data' => 'list_files']],
            [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]
        ]
    ];
}


/**
 * Generates a keyboard for the user management menu.
 *
 * @return array The keyboard markup.
 */
function getUserManagementKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '📋 查看用户列表', 'callback_data' => 'list_users']],
            [['text' => '🗑️ 删除用户', 'callback_data' => 'delete_user_prompt']],
            [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]
        ]
    ];
}

/**
 * Generates a keyboard for selecting which API key to update.
 *
 * @return array The keyboard markup.
 */
function getApiKeySelectionKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '💎 Gemini API Key', 'callback_data' => 'set_api_key_GEMINI_API_KEY']],
            // Add other keys here if needed in the future
            [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]
        ]
    ];
}