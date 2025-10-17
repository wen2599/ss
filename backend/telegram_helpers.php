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
 * 发送彩票结果到指定的频道
 *
 * @param array $lotteryInfo 包含期号、号码、日期等信息的数组
 * @return bool
 */
function sendLotteryResultToChannel($lotteryInfo) {
    $lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');
    if (empty($lotteryChannelId)) {
        error_log("LOTTERY_CHANNEL_ID is not configured. Cannot send lottery result.");
        return false;
    }

    $issue = htmlspecialchars($lotteryInfo['issue'] ?? 'N/A');
    $numbers = htmlspecialchars($lotteryInfo['numbers'] ?? 'N/A');
    $drawDate = htmlspecialchars($lotteryInfo['draw_date'] ?? 'N/A');

    $message = "🎉 **最新开奖结果** 🎉\n\n";
    $message .= "**期号:** #{$issue}\n";
    $message .= "**开奖号码:** `{$numbers}`\n";
    $message .= "**开奖日期:** {$drawDate}\n\n";
    $message .= "祝您好运！🍀";

    return sendTelegramMessage($lotteryChannelId, $message);
}

/**
 * 处理彩票频道接收到的消息
 * 尝试从消息中解析彩票结果并存储
 *
 * @param int|string $chatId
 * @param string $messageText
 * @return void
 */
function handleLotteryMessage($db_getter, $chatId, $messageText) {
    write_telegram_debug_log("Attempting to handle lottery message: {$messageText}");

    $pdo = $db_getter();
    if (is_array($pdo) && isset($pdo['db_error'])) {
        write_telegram_debug_log("DB connection error in handleLotteryMessage: " . $pdo['db_error']);
        // Optionally notify admin
        sendTelegramMessage($chatId, "处理彩票信息时数据库连接失败。");
        return;
    }

    $issue = null; // 期号
    $numbers = null; // 号码
    $drawDate = date('Y-m-d'); // 默认开奖日期为今天

    // 尝试从消息中解析期号，例如 "第12345期"
    if (preg_match('/第(\d+)期/', $messageText, $matches)) {
        $issue = $matches[1];
    }

    // 尝试从消息中解析开奖号码，例如 "号码：01,02,03,04,05,06+07"
    if (preg_match('/号码[：:]\s*([\d,\s+]+)/u', $messageText, $matches)) {
        $numbers = trim($matches[1]);
    }

    if ($issue && $numbers) {
        // 假设有一个 storeLotteryResult 函数可以存储结果
        // 需要确保 db_operations.php 已经被 require_once
        if (function_exists('storeLotteryResult')) {
            storeLotteryResult('lottery', $issue, $numbers, '', '', $drawDate);
            write_telegram_debug_log("Stored lottery result for issue {$issue} with numbers {$numbers}");
            
            // 存储后，立即发送到频道
            sendLotteryResultToChannel([
                'issue' => $issue,
                'numbers' => $numbers,
                'draw_date' => $drawDate
            ]);

        } else {
            write_telegram_debug_log("storeLotteryResult function not found. Cannot store lottery data.");
        }
    } else {
        write_telegram_debug_log("Could not parse lottery issue or numbers from message: {$messageText}");
    }
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

// This function will handle user interactions that require multiple steps
function handleStatefulInteraction($db_getter, $userId, $chatId, $commandOrText, $userState) {
    write_telegram_debug_log("Handling state for user {$userId}: {$userState}");

    // States that need DB connection
    $db_dependent_states = ['awaiting_user_deletion'];

    $pdo = null;
    if (in_array($userState, $db_dependent_states)) {
        $pdo = call_user_func($db_getter);
        if (is_array($pdo) && isset($pdo['db_error'])) {
            write_telegram_debug_log("DB connection error in handleStatefulInteraction: " . $pdo['db_error']);
            sendTelegramMessage($chatId, "数据库操作失败，请检查日志。", getAdminKeyboard());
            setUserState($userId, null); // Reset state
            return;
        }
    }

    // Example: awaiting API key input (no DB needed)
    if (strpos($userState, 'awaiting_api_key_') === 0) {
        $keyName = substr($userState, strlen('awaiting_api_key_'));
        if (update_env_file($keyName, $commandOrText)) {
            sendTelegramMessage($chatId, "✅ API 密钥 {$keyName} 已成功更新！", getAdminKeyboard());
        } else {
            sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！请确保 .env 文件可写。", getAdminKeyboard());
        }
        setUserState($userId, null);
        return;
    }

    // Example: awaiting AI prompt (no DB needed)
    if ($userState === 'awaiting_gemini_prompt' || $userState === 'awaiting_cloudflare_prompt') {
        sendTelegramMessage($chatId, "🧠 正在处理，请稍候...");
        $response = ($userState === 'awaiting_gemini_prompt') ? call_gemini_api($commandOrText) : call_cloudflare_ai_api($commandOrText);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        setUserState($userId, null);
        return;
    }

    // Example: awaiting user deletion email (needs DB)
    if ($userState === 'awaiting_user_deletion') {
        if (filter_var($commandOrText, FILTER_VALIDATE_EMAIL)) {
            if (deleteUserByEmail($pdo, $commandOrText)) { // Pass PDO object
                sendTelegramMessage($chatId, "✅ 用户 {$commandOrText} 已成功删除。", getUserManagementKeyboard());
            } else {
                sendTelegramMessage($chatId, "⚠️ 删除失败。用户不存在或数据库错误。", getUserManagementKeyboard());
            }
        } else {
            sendTelegramMessage($chatId, "❌ 无效的电子邮件地址，请重新输入。", getUserManagementKeyboard());
        }
        setUserState($userId, null);
        return;
    }

    // Fallback for unknown states
    sendTelegramMessage($chatId, "系统状态异常，已重置。", getAdminKeyboard());
    setUserState($userId, null);
}


// This function will handle direct commands (not stateful)
function processCommand($db_getter, $userId, $chatId, $commandOrText, $isCallback) {
    write_telegram_debug_log("Processing command for user {$userId}: {$commandOrText}");
    $reply = null;
    $replyKeyboard = null;

    // Handle callback_data for API key selection
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
            $blacklist = ['.', '..', '.env', '.env.example', '.git', '.gitignore', '.htaccess', 'vendor', 'composer.lock', 'debug.log'];
            $text = "📁 当前目录文件列表:\n\n";
            foreach ($files as $f) {
                if (!in_array($f, $blacklist, true)) $text .= htmlspecialchars($f) . "\n";
            }
            $reply = $text;
            $replyKeyboard = getFileManagementKeyboard();
            break;
        case 'list_users':
            $pdo = call_user_func($db_getter);
            if (is_array($pdo) && isset($pdo['db_error'])) {
                $reply = "数据库连接失败，无法获取用户列表。";
                write_telegram_debug_log("DB Error on list_users: " . $pdo['db_error']);
            } else {
                $users = getAllUsers($pdo); // Pass PDO object
                if (empty($users)) {
                    $reply = "数据库中未找到用户。";
                } else {
                    $text = "👥 注册用户列表:\n\n";
                    foreach ($users as $u) {
                        $text .= "📧 " . htmlspecialchars($u['email']) . " (注册于: " . htmlspecialchars($u['created_at']) . ")\n";
                    }
                    $reply = $text;
                }
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
