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
 * Processes incoming commands from the user.
 * This is a temporary diagnostic version.
 */
function processCommand($chatId, $userId, $command) {
    sendTelegramMessage($chatId, "DIAGNOSTIC TEST: The processCommand function was called successfully. The script is running. The error is in the command logic or file load order.");
}

// The original handleStatefulInput and processCommand functions are commented out below for preservation.
/*
function handleStatefulInput($chatId, $userId, $text, $state) {
    if ($state === 'awaiting_gemini_api_key') {
        if (update_env_file('GEMINI_API_KEY', $text)) {
            sendTelegramMessage($chatId, "✅ Gemini API 密钥已成功更新。", getAdminKeyboard());
        } else {
            sendTelegramMessage($chatId, "❌ 更新 Gemini API 密钥失败。请检查文件权限。", getAdminKeyboard());
        }
        setUserState($userId, null);
    } elseif ($state === 'awaiting_user_to_delete') {
        if (deleteUserByEmail($text)) {
            sendTelegramMessage($chatId, "✅ 用户 '{$text}' 已成功删除。", getUserManagementKeyboard());
        } else {
            sendTelegramMessage($chatId, "❌ 删除用户 '{$text}' 失败。用户可能不存在或数据库出错。", getUserManagementKeyboard());
        }
        setUserState($userId, null);
    } elseif ($state === 'awaiting_gemini_prompt') {
        $response = call_gemini_api($text);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        setUserState($userId, null);
    } elseif ($state === 'awaiting_cloudflare_prompt') {
        $response = call_cloudflare_ai_api($text);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        setUserState($userId, null);
    } else {
        sendTelegramMessage($chatId, "🤔 未知的状态，已重置。请重新开始。", getAdminKeyboard());
        setUserState($userId, null);
    }
}

function processCommand($chatId, $userId, $command) {
    $state = getUserState($userId);
    if ($state) {
        handleStatefulInput($chatId, $userId, $command, $state);
        return;
    }
    switch ($command) {
        case '/start':
            sendTelegramMessage($chatId, "你好！欢迎使用管理机器人。", getAdminKeyboard());
            break;
        case '返回主菜单':
            setUserState($userId, null);
            sendTelegramMessage($chatId, "返回主菜单。", getAdminKeyboard());
            break;
        case '用户管理':
            sendTelegramMessage($chatId, "请选择一个用户管理操作:", getUserManagementKeyboard());
            break;
        case '查看用户列表':
            $users = getAllUsers();
            if (empty($users)) {
                $message = "没有已注册的用户。";
            } else {
                $message = "<b>用户列表:</b>\n\n";
                foreach ($users as $user) {
                    $message .= "• <b>邮箱:</b> <code>" . htmlspecialchars($user['email']) . "</code>\n";
                    $message .= "  <b>注册时间:</b> " . $user['created_at'] . "\n\n";
                }
            }
            sendTelegramMessage($chatId, $message, getUserManagementKeyboard());
            break;
        case '删除用户':
            setUserState($userId, 'awaiting_user_to_delete');
            sendTelegramMessage($chatId, "请输入要删除用户的邮箱地址:", ['remove_keyboard' => true]);
            break;
        case '文件管理':
            sendTelegramMessage($chatId, "请选择一个文件管理操作:", getFileManagementKeyboard());
            break;
        case '列出文件':
            $output = shell_exec('ls -la');
            sendTelegramMessage($chatId, "<pre>" . htmlspecialchars($output) . "</pre>", getFileManagementKeyboard());
            break;
        case '更换 API 密钥':
            sendTelegramMessage($chatId, "请选择要更换的 API 密钥:", getApiKeySelectionKeyboard());
            break;
        case 'Gemini API Key':
            setUserState($userId, 'awaiting_gemini_api_key');
            sendTelegramMessage($chatId, "请输入新的 Gemini API 密钥:", ['remove_keyboard' => true]);
            break;
        case '请求 Gemini':
            setUserState($userId, 'awaiting_gemini_prompt');
            sendTelegramMessage($chatId, "请输入你的 Gemini 提示:", ['remove_keyboard' => true]);
            break;
        case '请求 Cloudflare':
            setUserState($userId, 'awaiting_cloudflare_prompt');
            sendTelegramMessage($chatId, "请输入你的 Cloudflare AI 提示:", ['remove_keyboard' => true]);
            break;
        default:
            sendTelegramMessage($chatId, "未知命令。请使用键盘上的选项。", getAdminKeyboard());
            break;
    }
}
*/


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