<?php
/**
 * telegramWebhook.php
 * 更稳健的 webhook 实现，增强日志与兼容性（header / param secret fallback）
 */

// 极早期日志，便于排查 header 丢失等问题
$earlyLogFile = __DIR__ . '/telegram_early_debug.log';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN_METHOD';
$requestUri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN_URI';
$secretTokenHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '[HEADER_NOT_SET]';
file_put_contents(
    $earlyLogFile,
    date('[Y-m-d H:i:s]') . " [EARLY_WEBHOOK_DEBUG] Method: {$requestMethod}, URI: {$requestUri}, X-Telegram-Bot-Api-Secret-Token: '{$secretTokenHeader}'\n",
    FILE_APPEND | LOCK_EX
);

// 加载配置与辅助函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/env_manager.php';
require_once __DIR__ . '/user_state_manager.php';

// 调试日志函数
function write_telegram_debug_log($message) {
    $logFile = __DIR__ . '/telegram_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . ' [TELEGRAM_WEBHOOK] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

write_telegram_debug_log("------ Webhook Entry Point ------");

// 读取并记录装载到环境的关键变量（不打印 secret 的明文）
$loadedSecret = getenv('TELEGRAM_WEBHOOK_SECRET');
$loadedAdminId = getenv('TELEGRAM_ADMIN_ID');
$loadedLotteryChannelId = getenv('LOTTERY_CHANNEL_ID');
write_telegram_debug_log("Loaded TELEGRAM_WEBHOOK_SECRET: " . ($loadedSecret ? '***' : '[Not Set]'));
write_telegram_debug_log("Loaded TELEGRAM_ADMIN_ID: " . ($loadedAdminId ?: '[Not Set]'));
write_telegram_debug_log("Loaded LOTTERY_CHANNEL_ID: " . ($loadedLotteryChannelId ?: '[Not Set]'));

// ------------------ 验证 webhook secret ------------------
$secretTokenHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
$secretTokenParam = $_GET['secret'] ?? ($_POST['secret'] ?? null); // 允许通过 GET/POST 传递 secret（兼容）
// 优先使用 header，再回退到 param（回退会记录警告）
$receivedToken = $secretTokenHeader ?? $secretTokenParam ?? '';

if (empty($loadedSecret)) {
    write_telegram_debug_log("WARNING: TELEGRAM_WEBHOOK_SECRET is NOT set in environment. Webhook will not perform secret validation.");
} else {
    if (!$receivedToken) {
        write_telegram_debug_log("Webhook rejected: No secret token provided. Header and param empty.");
        http_response_code(403);
        exit('Forbidden: Missing secret token.');
    }
    // 如果 header 与 env 不匹配，允许 param 回退但记录
    if ($receivedToken !== $loadedSecret) {
        write_telegram_debug_log("Webhook Forbidden: Token mismatch. Received token (preview): " . substr($receivedToken,0,8) . "... Expected: ***");
        http_response_code(403);
        exit('Forbidden: Secret token mismatch.');
    }
}
write_telegram_debug_log("Webhook secret validation passed.");

// 读取 update body
$bodyRaw = file_get_contents('php://input');
$update = json_decode($bodyRaw, true);

// 记录原始 cập nhật 用于排查
write_telegram_debug_log("Raw update payload: " . (strlen($bodyRaw) > 0 ? substr($bodyRaw, 0, 4000) : '[empty]'));

// 如果不是有效 JSON，直接返回 200（Telegram 要求 200），并记录
if (!is_array($update)) {
    write_telegram_debug_log("Invalid JSON payload received; ignoring.");
    http_response_code(200);
    exit();
}

// 解析常见类型
$chatId = null;
$userId = null;
$command = null;

if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'] ?? null;
    $userId = $callbackQuery['from']['id'] ?? null;
    $command = $callbackQuery['data'] ?? null;
    write_telegram_debug_log("Received callback_query. ChatId={$chatId}, UserId={$userId}, Data={$command}");

    // 先回应 callback query 的 loading 状态（非阻塞）
    answerTelegramCallbackQuery($callbackQuery['id'] ?? null);

} elseif (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'] ?? null;
    $userId = $message['from']['id'] ?? $chatId;
    $command = trim($message['text'] ?? '');
    write_telegram_debug_log("Received message. ChatId={$chatId}, UserId={$userId}, Text=" . substr($command,0,400));

    // 如果是来自开奖频道的消息（自动处理开奖），优先处理并退出
    $lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');
    if ($lotteryChannelId && (string)$chatId === (string)$lotteryChannelId) {
        write_telegram_debug_log("Message from lottery channel ({$lotteryChannelId}). Handling as lottery message.");
        handleLotteryMessage($chatId, $command);
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Lottery message processed.']);
        exit();
    }

} else {
    // 其它类型（edited_message, etc.）暂忽略
    write_telegram_debug_log("Unsupported update type received; ignoring.");
    http_response_code(200);
    exit();
}

// 如果 chatId 或 userId 缺失，记录并退出
if (empty($chatId) || empty($userId)) {
    write_telegram_debug_log("Missing chatId or userId after parsing update. chatId=" . var_export($chatId, true) . ", userId=" . var_export($userId, true));
    http_response_code(200);
    exit();
}

// 管理员权限校验（仅管理员可操作机器人交互界面）
$adminChatId = getenv('TELEGRAM_ADMIN_ID');
if (empty($adminChatId)) {
    write_telegram_debug_log("WARNING: TELEGRAM_ADMIN_ID not configured; permitting all users for admin commands (unsafe).");
} else {
    if ((string)$chatId !== (string)$adminChatId) {
        write_telegram_debug_log("Unauthorized access attempt from chat ID: {$chatId}. Expected Admin ID: {$adminChatId}");
        // 友好地通知用户无权限
        sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
        http_response_code(200);
        exit();
    }
}

// 处理命令与状态机
try {
    processCommand($chatId, $userId, $command, $update);
} catch (Throwable $e) {
    write_telegram_debug_log("Exception in processCommand: " . $e->getMessage());
    // 向管理员发一条简短提示，避免泄漏错误细节
    sendTelegramMessage($chatId, "出现内部错误，请查看服务器日志以获取更多信息。");
}

// 确认响应 Telegram
http_response_code(200);
echo json_encode(['status' => 'ok']);
exit();

/**
 * processCommand: 封装后的命令处理器
 */
function processCommand($chatId, $userId, $command, $update) {
    $userState = getUserState($userId);
    if ($userState) {
        handleStatefulInteraction($chatId, $userId, $command, $userState);
    } else {
        handleCommand($chatId, $userId, $command, $update);
    }
}

/**
 * 状态交互处理
 */
function handleStatefulInteraction($chatId, $userId, $text, $userState) {
    if (strpos($userState, 'awaiting_api_key_') === 0) {
        $keyToUpdate = substr($userState, strlen('awaiting_api_key_'));
        if (update_env_file($keyToUpdate, $text)) {
            sendTelegramMessage($chatId, "✅ API 密钥 {$keyToUpdate} 已成功更新！", getAdminKeyboard());
        } else {
            sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！请确保服务器上的 .env 可写。", getAdminKeyboard());
        }
        setUserState($userId, null);
        return;
    }

    if ($userState === 'awaiting_gemini_prompt' || $userState === 'awaiting_cloudflare_prompt') {
        sendTelegramMessage($chatId, "🧠 正在处理，请稍候...");
        $response = ($userState === 'awaiting_gemini_prompt') ? call_gemini_api($text) : call_cloudflare_ai_api($text);
        sendTelegramMessage($chatId, $response, getAdminKeyboard());
        setUserState($userId, null);
        return;
    }

    if ($userState === 'awaiting_user_deletion') {
        if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
            if (deleteUserByEmail($text)) {
                sendTelegramMessage($chatId, "✅ 用户 {$text} 已成功删除。", getUserManagementKeyboard());
            } else {
                sendTelegramMessage($chatId, "⚠️ 删除失败。请检查该用户是否存在或查看服务器日志。", getUserManagementKeyboard());
            }
        } else {
            sendTelegramMessage($chatId, "❌ 无效的电子邮件地址，请重新输入。", getUserManagementKeyboard());
        }
        setUserState($userId, null);
        return;
    }

    // 默认回退：清理状态并提示
    setUserState($userId, null);
    sendTelegramMessage($chatId, "系统状态异常，已重置。", getAdminKeyboard());
}

/**
 * 无状态命令处理
 */
function handleCommand($chatId, $userId, $command, $update) {
    $messageToSend = null;
    $keyboard = null;

    // 精确匹配常用命令
    switch ($command) {
        case '/start':
        case 'main_menu':
            $messageToSend = "欢迎回来，管理员！请选择一个操作。";
            $keyboard = getAdminKeyboard();
            break;
        case 'menu_user_management':
            $messageToSend = "请选择一个用户管理操作:";
            $keyboard = getUserManagementKeyboard();
            break;
        case 'menu_file_management':
            $messageToSend = "请选择一个文件管理操作:";
            $keyboard = getFileManagementKeyboard();
            break;
        case 'menu_api_keys':
            $messageToSend = "请选择您想要更新的 API 密钥：";
            $keyboard = getApiKeySelectionKeyboard();
            break;
        case 'list_files':
            $files = scandir(__DIR__);
            $blacklist = ['.', '..', '.env', '.env.example', '.git', '.gitignore', '.htaccess', 'vendor', 'composer.lock', 'debug.log'];
            $messageToSend = "📁 当前目录文件列表:\n\n";
            foreach ($files as $file) {
                if (!in_array($file, $blacklist, true)) $messageToSend .= $file . "\n";
            }
            $keyboard = getFileManagementKeyboard();
            break;
        case 'list_users':
            $users = getAllUsers();
            if (empty($users)) {
                $messageToSend = "数据库中未找到用户。";
            } else {
                $messageToSend = "👥 注册用户列表:\n\n";
                foreach ($users as $u) {
                    $messageToSend .= "📧 {$u['email']} (注册于: {$u['created_at']})\n";
                }
            }
            $keyboard = getUserManagementKeyboard();
            break;
        case 'delete_user_prompt':
            setUserState($userId, 'awaiting_user_deletion');
            $messageToSend = "请输入要删除的用户邮箱地址：";
            break;
        case 'ask_gemini':
        case 'ask_cloudflare':
            $state = ($command === 'ask_gemini') ? 'awaiting_gemini_prompt' : 'awaiting_cloudflare_prompt';
            setUserState($userId, $state);
            $messageToSend = "好的，请输入您的请求内容：";
            break;
        default:
            if (!empty($command)) {
                $messageToSend = "无法识别的命令 '{$command}'。请使用下方菜单。";
                $keyboard = getAdminKeyboard();
            }
            break;
    }

    if ($messageToSend) {
        sendTelegramMessage($chatId, $messageToSend, $keyboard);
    }
}

/**
 * 处理来自开奖频道的消息并保存到数据库
 */
function handleLotteryMessage($chatId, $messageText) {
    write_telegram_debug_log("Attempting to parse lottery message: " . substr($messageText,0,1200));

    $lottery_type = '未知彩票';
    $issue_number = '';
    $winning_numbers = '';
    $zodiac_signs = '';
    $colors = '';
    $drawing_date = date('Y-m-d');

    if (preg_match('/【(.*?)】第(\d+)期开奖结果/', $messageText, $matches)) {
        $lottery_type = trim($matches[1]);
        $issue_number = trim($matches[2]);
    }

    if (preg_match('/号码[：:]\s*([0-9\s]+)(?:\s+特\s*([0-9]+))?/u', $messageText, $matches)) {
        $numbers = trim($matches[1] . ' ' . ($matches[2] ?? ''));
        $winning_numbers = preg_replace('/\s+/', ' ', $numbers);
    }

    if (preg_match('/生肖[：:]\s*(.*)/u', $messageText, $matches)) {
        $zodiac_signs = trim($matches[1]);
    }

    if (preg_match('/颜色[：:]\s*(.*)/u', $messageText, $matches)) {
        $colors = trim($matches[1]);
    }

    if (preg_match('/开奖日期[：:]\s*(\d{4}-\d{2}-\d{2})/', $messageText, $matches)) {
        $drawing_date = trim($matches[1]);
    }

    write_telegram_debug_log("Parsed lottery: type={$lottery_type}, issue={$issue_number}, numbers={$winning_numbers}, zodiac={$zodiac_signs}, colors={$colors}, date={$drawing_date}");

    $result = storeLotteryResult($lottery_type, $issue_number, $winning_numbers, $zodiac_signs, $colors, $drawing_date);
    if ($result) {
        write_telegram_debug_log("Lottery result stored for {$lottery_type} issue {$issue_number}");
    } else {
        write_telegram_debug_log("Failed to store lottery result for {$lottery_type} issue {$issue_number}");
    }
}

?>
