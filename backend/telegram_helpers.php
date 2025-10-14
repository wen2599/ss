<?php

/**
 * Sends a message to a specific Telegram chat.
 *
 * @param int        $chatId      The ID of the chat to send the message to.
 * @param string     $text        The message text.
 * @param array|null $replyMarkup Optional. A keyboard markup object.
 * @return bool True on success, false on failure.
 */
function sendTelegramMessage($chatId, $text, $replyMarkup = null) {
    $token = getenv('TELEGRAM_BOT_TOKEN');
    if (empty($token) || $token === 'your_telegram_bot_token_here') {
        error_log("CRITICAL: sendTelegramMessage failed because TELEGRAM_BOT_TOKEN is not configured.");
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML', // Change to HTML mode, which is more forgiving.
    ];

    if ($replyMarkup) {
        $payload['reply_markup'] = json_encode($replyMarkup);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Telegram API error: {$http_code} - {$response}");
        return false;
    }

    return true;
}

/**
 * Generates the main admin keyboard with all top-level functions.
 *
 * @return array The keyboard markup.
 */
/**
 * Answers a Telegram callback query (e.g., after a button press).
 *
 * @param string $callbackQueryId The ID of the callback query.
 * @param string|null $text       The text to show as a notification. Can be null.
 * @return bool True on success, false on failure.
 */
function answerCallbackQuery($callbackQueryId, $text = null) {
    $token = getenv('TELEGRAM_BOT_TOKEN');
    if (empty($token)) {
        error_log("CRITICAL: answerCallbackQuery failed because TELEGRAM_BOT_TOKEN is not configured.");
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
    $payload = ['callback_query_id' => $callbackQueryId];
    if ($text) {
        $payload['text'] = $text;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Telegram API error (answerCallbackQuery): {$http_code} - {$response}");
        return false;
    }
    return true;
}

/**
 * Generates the main admin keyboard with all top-level functions.
 *
 * @return array The keyboard markup.
 */
function getAdminKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '👤 用户管理', 'callback_data' => 'user_management']],
            [['text' => '🧠 请求 Gemini', 'callback_data' => 'ask_gemini'], ['text' => '☁️ 请求 Cloudflare', 'callback_data' => 'ask_cloudflare']],
            [['text' => '🔑 更换 API 密钥', 'callback_data' => 'change_api_key']],
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
            [['text' => '🗑️ 删除用户', 'callback_data' => 'delete_user']],
            [['text' => '⬅️ 返回主菜单', 'callback_data' => 'main_menu']]
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
            [['text' => '🔑 Gemini API Key', 'callback_data' => 'set_api_key_GEMINI_API_KEY']],
            [['text' => '⬅️ 返回主菜单', 'callback_data' => 'main_menu']]
        ]
    ];
}