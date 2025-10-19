<?php
/**
 * telegram_helpers.php
 * 增强版 Telegram 请求与帮助函数
 */

function sendTelegramRequest($method, $data) {
    $token = getenv('TELEGRAM_BOT_TOKEN');
    if (empty($token) || $token === 'your_telegram_bot_token_here') {
        error_log("CRITICAL: Telegram request failed because TELEGRAM_BOT_TOKEN is not configured.");
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/{$method}";

    // 使用 JSON 发请求，Telegram 支持 application/json
    $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 加长一点超时时间

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Telegram cURL error for method {$method}: {$error}");
        return false;
    }

    if ($http_code !== 200) {
        error_log("Telegram API HTTP error for method {$method}: HTTP {$http_code} - Response: {$response}");
        return false;
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Telegram API: invalid JSON response for method {$method}: " . json_last_error_msg());
        return false;
    }

    if (isset($decoded['ok']) && $decoded['ok'] === true) {
        return $decoded;
    } else {
        // Telegram 返回了 ok=false，记录详细内容
        error_log("Telegram API returned ok=false for method {$method}. Full response: " . $response);
        return false;
    }
}

/**
 * 发送文本消息到 chatId
 *
 * @param int|string $chatId
 * @param string $text
 * @param array|null $replyMarkup
 * @return bool
 */
function sendTelegramMessage($chatId, $text, $replyMarkup = null) {
    if (empty($chatId)) {
        error_log("sendTelegramMessage called with empty chatId. Text: " . substr($text, 0, 200));
        return false;
    }

    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];

    if ($replyMarkup) {
        // 如果传入的是 array，确保在发送之前是 JSON 编码的字符串（sendTelegramRequest 会做 JSON）
        $payload['reply_markup'] = $replyMarkup;
    }

    $result = sendTelegramRequest('sendMessage', $payload);
    if ($result === false) {
        error_log("sendTelegramMessage failed for chatId {$chatId}. Text preview: " . substr($text, 0, 200));
        return false;
    }
    return true;
}

/**
 * 回应 callback_query，停止按钮等待状态
 *
 * @param string $callbackQueryId
 * @param string|null $text
 * @return bool
 */
function answerTelegramCallbackQuery($callbackQueryId, $text = null) {
    if (empty($callbackQueryId)) return false;
    $payload = [
        'callback_query_id' => $callbackQueryId,
    ];
    if ($text !== null) $payload['text'] = $text;
    return sendTelegramRequest('answerCallbackQuery', $payload) !== false;
}

/**
 * 管理员键盘：返回数组结构，sendTelegramRequest 会序列化为 JSON
 */
function getAdminKeyboard() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '👤 用户管理', 'callback_data' => 'menu_user_management'],
                ['text' => '📁 文件管理', 'callback_data' => 'menu_file_management']
            ],
            [
                ['text' => '🧠 请求 Gemini', 'callback_data' => 'ask_gemini'],
                ['text' => '☁️ 请求 Cloudflare', 'callback_data' => 'ask_cloudflare']
            ],
            [
                ['text' => '🔑 更换 API 密钥', 'callback_data' => 'menu_api_keys']
            ]
        ]
    ];
}

function getFileManagementKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '👁️ 列出文件', 'callback_data' => 'list_files']],
            [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]
        ]
    ];
}

function getUserManagementKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '📋 查看用户列表', 'callback_data' => 'list_users']],
            [['text' => '🗑️ 删除用户', 'callback_data' => 'delete_user_prompt']],
            [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]
        ]
    ];
}

function getApiKeySelectionKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '💎 Gemini API Key', 'callback_data' => 'set_api_key_GEMINI_API_KEY']],
            [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]
        ]
    ];
}

/**
 * A simplified keyboard that only shows a "Main Menu" button.
 * Useful for when the user is in a specific state and needs a way to exit.
 */
function getSimpleMainMenuKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]
        ]
    ];
}
?>
