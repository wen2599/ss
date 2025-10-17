<?php
/**
 * telegram_helpers.php
 * 增强版 Telegram 请求与帮助函数
 */

// Function to write to a dedicated telegram debug log
function write_telegram_debug_log($message) {
    $logFile = __DIR__ . '/telegram_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' [TELEGRAM] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function sendTelegramRequest($method, $data) {
    $token = getenv('TELEGRAM_BOT_TOKEN');
    if (empty($token) || $token === 'your_telegram_bot_token_here') {
        error_log("CRITICAL: Telegram request failed because TELEGRAM_BOT_TOKEN is not configured.");
        write_telegram_debug_log("CRITICAL: TELEGRAM_BOT_TOKEN is not configured.");
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $payloadJson = json_encode($data, JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        $log_msg = "Telegram cURL error for method {$method}: {$error}";
        error_log($log_msg);
        write_telegram_debug_log($log_msg);
        return false;
    }

    if ($http_code !== 200) {
        $log_msg = "Telegram API HTTP error for method {$method}: HTTP {$http_code} - Response: {$response}";
        error_log($log_msg);
        write_telegram_debug_log($log_msg);
    }

    return $response;
}

function sendTelegramMessage($chatId, $text, $replyMarkup = null) {
    if (empty($chatId)) {
        $log_msg = "sendTelegramMessage called with empty chatId. Text: " . substr($text, 0, 200);
        error_log($log_msg);
        write_telegram_debug_log($log_msg);
        return false;
    }

    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];

    if ($replyMarkup) {
        $payload['reply_markup'] = $replyMarkup;
    }

    $rawResponse = sendTelegramRequest('sendMessage', $payload);
    if ($rawResponse === false) {
        write_telegram_debug_log("sendTelegramMessage failed for chatId {$chatId} due to critical send error.");
        return false;
    }

    $decodedResponse = json_decode($rawResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($decodedResponse['ok']) || $decodedResponse['ok'] !== true) {
        write_telegram_debug_log("sendTelegramMessage received invalid or error response for chatId {$chatId}. Raw response: " . $rawResponse);
        return false;
    }
    return true;
}

function answerTelegramCallbackQuery($callbackQueryId, $text = null) {
    if (empty($callbackQueryId)) return false;
    $payload = ['callback_query_id' => $callbackQueryId];
    if ($text !== null) $payload['text'] = $text;
    
    $rawResponse = sendTelegramRequest('answerCallbackQuery', $payload);
    if ($rawResponse === false) return false;

    $decodedResponse = json_decode($rawResponse, true);
    return (json_last_error() === JSON_ERROR_NONE && isset($decodedResponse['ok']) && $decodedResponse['ok'] === true);
}


/**
 * [REBUILT] Handles messages from the lottery channel, parses them, and stores the results.
 * This is the core logic for data entry.
 *
 * @param string $messageText The full text of the message from the channel.
 * @return void
 */
function handleLotteryMessage($messageText) {
    write_telegram_debug_log("Received channel message. Attempting to parse and store lottery result.");
    write_telegram_debug_log("Original Message Text: " . $messageText);

    // 1. Parse Lottery Type (彩票种类)
    $lotteryType = null;
    if (strpos($messageText, '新澳门六合彩') !== false) {
        $lotteryType = '新澳门六合彩';
    } elseif (strpos($messageText, '香港六合彩') !== false) {
        $lotteryType = '香港六合彩';
    } elseif (strpos($messageText, '老澳门六合彩') !== false) {
        $lotteryType = '老澳门六合彩';
    }

    // 2. Parse Issue Number (期号)
    $issue = null;
    if (preg_match('/第\s*(\d+)\s*期/', $messageText, $matches)) {
        $issue = $matches[1];
    }

    // 3. Parse Winning Numbers (开奖号码)
    $numbers = null;
    if (preg_match('/(?:号码|开奖号码|特码)\s*[：:]\s*([\d,\s+]+)/u', $messageText, $matches)) {
        $numbers = preg_replace('/\s+/', '', $matches[1]); // Remove all whitespace
    }
    
    // 4. Parse Drawing Date (开奖日期)
    $drawDate = date('Y-m-d'); // Default to today
    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $messageText, $dateMatches)) {
        $drawDate = $dateMatches[1];
    }

    // 5. Validate and Store
    if ($lotteryType && $issue && $numbers) {
        write_telegram_debug_log("Parsing successful: Type='{$lotteryType}', Issue='{$issue}', Numbers='{$numbers}', Date='{$drawDate}'.");
        
        // Ensure db_operations.php is available. It should be included via config.php.
        if (function_exists('storeLotteryResult')) {
            // This is the crucial call to store the correctly parsed data.
            $success = storeLotteryResult($lotteryType, $issue, $numbers, '' /* zodiac */, '' /* colors */, $drawDate);
            
            if ($success) {
                write_telegram_debug_log("Database storage call successful for issue {$issue}.");
            } else {
                write_telegram_debug_log("Database storage call failed for issue {$issue}. See main error log for details.");
            }
        } else {
            write_telegram_debug_log("CRITICAL: storeLotteryResult() function not found! Cannot store data.");
        }
    } else {
        write_telegram_debug_log("Parsing failed. Could not extract all required fields from message.");
    }
}


function getAdminKeyboard() {
    return ['inline_keyboard' => [[['text' => '👤 用户管理', 'callback_data' => 'menu_user_management'], ['text' => '📁 文件管理', 'callback_data' => 'menu_file_management']], [['text' => '🧠 请求 Gemini', 'callback_data' => 'ask_gemini'], ['text' => '☁️ 请求 Cloudflare', 'callback_data' => 'ask_cloudflare']], [['text' => '🔑 更换 API 密钥', 'callback_data' => 'menu_api_keys']]]];
}

function getFileManagementKeyboard() {
    return ['inline_keyboard' => [[['text' => '👁️ 列出文件', 'callback_data' => 'list_files']], [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]]];
}

function getUserManagementKeyboard() {
    return ['inline_keyboard' => [[['text' => '📋 查看用户列表', 'callback_data' => 'list_users']], [['text' => '🗑️ 删除用户', 'callback_data' => 'delete_user_prompt']], [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]]];
}

function getApiKeySelectionKeyboard() {
    return ['inline_keyboard' => [[['text' => '💎 Gemini API Key', 'callback_data' => 'set_api_key_GEMINI_API_KEY']], [['text' => '🔙 返回主菜单', 'callback_data' => 'main_menu']]]];
}

function handleStatefulInteraction($conn, $userId, $chatId, $commandOrText, $userState) {
    write_telegram_debug_log("Handling state '{$userState}' for user {$userId}.");
    
    if (strpos($userState, 'awaiting_api_key_') === 0) {
        $keyName = substr($userState, strlen('awaiting_api_key_'));
        if (update_env_file($keyName, $commandOrText)) {
            sendTelegramMessage($chatId, "✅ API 密钥 {$keyName} 已成功更新！", getAdminKeyboard());
        } else {
            sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！请确保服务器上的 .env 文件可写。", getAdminKeyboard());
        }
        setUserState($userId, null);
        return;
    } 
    
    if ($userState === 'awaiting_gemini_prompt' || $userState === 'awaiting_cloudflare_prompt') {
        sendTelegramMessage($chatId, "🧠 正在处理，请稍候...");
        $response = ($userState === 'awaiting_gemini_prompt') ? call_gemini_api($commandOrText) : call_cloudflare_ai_api($commandOrText);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        setUserState($userId, null);
        return;
    }

    if ($userState === 'awaiting_user_deletion') {
        if (filter_var($commandOrText, FILTER_VALIDATE_EMAIL)) {
            if (deleteUserByEmail($commandOrText)) {
                sendTelegramMessage($chatId, "✅ 用户 {$commandOrText} 已成功删除。", getUserManagementKeyboard());
            } else {
                sendTelegramMessage($chatId, "⚠️ 删除失败。请检查该用户是否存在或查看服务器日志。", getUserManagementKeyboard());
            }
        } else {
            sendTelegramMessage($chatId, "❌ 无效的电子邮件地址，请重新输入。", getUserManagementKeyboard());
        }
        setUserState($userId, null);
        return;
    }
    
    sendTelegramMessage($chatId, "系统状态异常，已重置。", getAdminKeyboard());
    setUserState($userId, null);
}

function processCommand($conn, $userId, $chatId, $commandOrText, $isCallback) {
    write_telegram_debug_log("Processing admin command '{$commandOrText}' for user {$userId}.");
    $reply = null;
    $replyKeyboard = null;
    
    if ($isCallback && strpos($commandOrText, 'set_api_key_') === 0) {
        $keyToSet = substr($commandOrText, strlen('set_api_key_'));
        setUserState($userId, 'awaiting_api_key_' . $keyToSet);
        sendTelegramMessage($chatId, "请输入 {$keyToSet} 的新 API 密钥：");
        return;
    }

    switch (strtolower($commandOrText)) {
        case '/start':
        case 'main_menu':
            $reply = "欢迎回来，管理员！请选择一个操作。";
            $replyKeyboard = getAdminKeyboard();
            break;
        case 'menu_user_management':
            $reply = "请选择一个用户管理操作:";
            $replyKeyboard = getUserManagementKeyboard();
            break;
        case 'menu_file_management':
            $reply = "请选择一个文件管理操作:";
            $replyKeyboard = getFileManagementKeyboard();
            break;
        case 'menu_api_keys':
            $reply = "请选择您想要更新的 API 密钥：";
            $replyKeyboard = getApiKeySelectionKeyboard();
            break;
        case 'list_files':
            $files = scandir(__DIR__);
            $blacklist = ['.', '..', '.env', '.env.example', '.git', '.gitignore', '.htaccess', 'vendor', 'composer.lock', 'debug.log', 'telegram_debug.log', 'lottery_debug.log'];
            $text = "📁 当前目录文件列表:\n\n";
            foreach ($files as $f) {
                if (!in_array($f, $blacklist, true)) $text .= "<code>" . htmlspecialchars($f) . "</code>\n";
            }
            $reply = $text;
            $replyKeyboard = getFileManagementKeyboard();
            break;
        case 'list_users':
            $users = getAllUsers();
            if (empty($users)) {
                $reply = "数据库中未找到用户。";
            } else {
                $text = "👥 注册用户列表:\n\n";
                foreach ($users as $u) {
                    $text .= "📧 <code>" . htmlspecialchars($u['email']) . "</code> (注册于: " . htmlspecialchars($u['created_at']) . ")\n";
                }
                $reply = $text;
            }
            $replyKeyboard = getUserManagementKeyboard();
            break;
        case 'delete_user_prompt':
            setUserState($userId, 'awaiting_user_deletion');
            $reply = "请输入要删除的用户邮箱地址：";
            break;
        case 'ask_gemini':
        case 'ask_cloudflare':
            $stateTo = (strtolower($commandOrText) === 'ask_gemini') ? 'awaiting_gemini_prompt' : 'awaiting_cloudflare_prompt';
            setUserState($userId, $stateTo);
            $reply = "好的，请输入您的请求内容：";
            break;
        default:
            if (!empty($commandOrText) && !$isCallback) {
                $reply = "无法识别的命令 '" . htmlspecialchars($commandOrText) . "'。请使用下方菜单。";
                $replyKeyboard = getAdminKeyboard();
            }
            break;
    }

    if ($reply) {
        sendTelegramMessage($chatId, $reply, $replyKeyboard);
    }
}
?>