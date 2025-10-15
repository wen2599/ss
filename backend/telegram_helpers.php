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
        'parse_mode' => 'HTML', // Use HTML mode for better compatibility.
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
function getAdminKeyboard() {
    return [
        'keyboard' => [
            [['text' => '用户管理'], ['text' => '文件管理']],
            [['text' => '请求 Gemini'], ['text' => '请求 Cloudflare']],
            [['text' => '更换 API 密钥']],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
    ];
}

/**
 * Generates a keyboard for the file management menu.
 *
 * @return array The keyboard markup.
 */
function getFileManagementKeyboard() {
    return [
        'keyboard' => [
            [['text' => '列出文件']],
            [['text' => '返回主菜单']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ];
}


/**
 * Generates a keyboard for the user management menu.
 *
 * @return array The keyboard markup.
 */
function getUserManagementKeyboard() {
    return [
        'keyboard' => [
            [['text' => '查看用户列表']],
            [['text' => '删除用户']],
            [['text' => '返回主菜单']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ];
}

/**
 * Generates a keyboard for selecting which API key to update.
 *
 * @return array The keyboard markup.
 */
function getApiKeySelectionKeyboard() {
    return [
        'keyboard' => [
            [['text' => 'Gemini API Key']],
            [['text' => '返回主菜单']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ];
}