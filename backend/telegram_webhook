<?php
/**
 * telegramWebhook.php (robust replacement)
 *
 * Goals:
 * - Validate the Telegram secret token (header OR GET/POST fallback).
 * - Load environment variables directly from backend/.env (so webhook can validate
 *   before requiring config.php which may `exit` on misconfigured servers).
 * - Include only required helpers after env is loaded to avoid fatal exits.
 * - Add detailed early and runtime logging to backend/telegram_early_debug.log and backend/telegram_debug.log.
 * - Be tolerant to proxy/gateway header stripping: accept header, then `secret` param fallback.
 *
 * Replace the existing file with this full content.
 */

// --- Early, minimal logging (before doing anything) ---
$earlyLogFile = __DIR__ . '/telegram_early_debug.log';
$now = date('[Y-m-d H:i:s]');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
$headerPreview = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '[HEADER_NOT_SET]';
file_put_contents($earlyLogFile, "{$now} [EARLY] Method={$method}, URI={$uri}, HeaderPreview={$headerPreview}\n", FILE_APPEND | LOCK_EX);

// --- Helper: load .env into environment (lightweight, safe) ---
function load_env_file_simple($path) {
    if (!file_exists($path) || !is_readable($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return false;
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '#') === 0) continue;
        if (strpos($trim, '=') === false) continue;
        list($key, $value) = explode('=', $trim, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes
        if ((substr($value,0,1) === '"' && substr($value,-1) === '"') ||
            (substr($value,0,1) === "'" && substr($value,-1) === "'")) {
            $value = substr($value,1,-1);
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    return true;
}

// Load env early so we can validate secret without relying on config.php
$envPath = __DIR__ . '/.env';
load_env_file_simple($envPath);

// Small runtime logger
function write_telegram_debug_log($msg) {
    $logFile = __DIR__ . '/telegram_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}


// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// +++ START OF NEW CODE: The Lottery Parsing Function               +++
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

/**
 * Parses the raw text from the lottery channel message.
 *
 * @param string $text The raw message text.
 * @return array|null An associative array with parsed data, or null on failure.
 */
function parse_lottery_data($text) {
    $data = [
        'lottery_type'    => null,
        'issue_number'    => null,
        'winning_numbers' => [],
        'zodiac_signs'    => [],
        'colors'          => [],
        'drawing_date'    => date('Y-m-d') // Default to today's date
    ];

    // 1. Extract lottery type and issue number from the first line
    if (preg_match('/(新澳门六合彩|香港六合彩|老澳.*?)第:(\d+)期/', $text, $headerMatches)) {
        $lottery_type_raw = trim($headerMatches[1]);
        // Normalize the name for "老澳门六合彩"
        if (strpos($lottery_type_raw, '老澳') !== false) {
            $data['lottery_type'] = '老澳门六合彩';
        } else {
            $data['lottery_type'] = $lottery_type_raw;
        }
        $data['issue_number'] = $headerMatches[2];
    } else {
        write_telegram_debug_log("[Parser] Failed: Could not match header line.");
        return null; // Essential info missing
    }

    // 2. Split the text into lines and clean them up
    $lines = array_map('trim', explode("\n", trim($text)));
    $filteredLines = array_filter($lines, function($line) {
        return !empty($line);
    });
    // Re-index the array after filtering
    $filteredLines = array_values($filteredLines);
    
    // We expect at least 4 lines: header, numbers, zodiacs, colors
    if (count($filteredLines) < 4) {
        write_telegram_debug_log("[Parser] Failed: Not enough lines in message. Found: " . count($filteredLines));
        return null;
    }

    // 3. Extract data from specific lines (now much safer)
    // The second line (index 1) contains the numbers
    $data['winning_numbers'] = preg_split('/\s+/', $filteredLines[1]);
    
    // The third line (index 2) contains the zodiac signs
    $data['zodiac_signs'] = preg_split('/\s+/', $filteredLines[2]);

    // The fourth line (index 3) contains the colors
    $data['colors'] = preg_split('/\s+/', $filteredLines[3]);
    
    // 4. Validate that we have the same count for all arrays
    $num_count = count($data['winning_numbers']);
    if ($num_count === 0 || $num_count !== count($data['zodiac_signs']) || $num_count !== count($data['colors'])) {
        write_telegram_debug_log("[Parser] Failed: Mismatch in counts. Numbers: {$num_count}, Zodiacs: " . count($data['zodiac_signs']) . ", Colors: " . count($data['colors']));
        return null;
    }

    write_telegram_debug_log("[Parser] Success: Parsed issue {$data['issue_number']} for {$data['lottery_type']}");
    return $data;
}

/**
 * Main handler for messages from the lottery channel.
 * Parses the message and stores the structured data in the database.
 *
 * @param string $chatId The ID of the channel.
 * @param string $text The message text to process.
 */
function handleLotteryMessage($chatId, $text) {
    write_telegram_debug_log("Attempting to parse lottery message: " . substr($text, 0, 100) . "...");

    $parsedData = parse_lottery_data($text);

    if ($parsedData === null) {
        write_telegram_debug_log("Failed to parse lottery message. No data will be stored.");
        return;
    }

    // Check if storeLotteryResult function from db_operations.php is available
    if (!function_exists('storeLotteryResult')) {
        write_telegram_debug_log("CRITICAL ERROR: function storeLotteryResult() does not exist! Cannot save data.");
        return;
    }

    try {
        // Convert arrays to JSON strings for database storage
        $numbersJson = json_encode($parsedData['winning_numbers']);
        $zodiacsJson = json_encode($parsedData['zodiac_signs']);
        $colorsJson = json_encode($parsedData['colors']);

        // Call the function to store data in the database
        $success = storeLotteryResult(
            $parsedData['lottery_type'],
            $parsedData['issue_number'],
            $numbersJson,
            $zodiacsJson,
            $colorsJson,
            $parsedData['drawing_date']
        );

        if ($success) {
            write_telegram_debug_log("Successfully stored lottery result for issue {$parsedData['issue_number']}.");
        } else {
            write_telegram_debug_log("Failed to store lottery result for issue {$parsedData['issue_number']}. Check db_operations.php and database logs.");
        }

    } catch (Throwable $e) {
        write_telegram_debug_log("Exception during database storage: " . $e->getMessage());
    }
}

// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// +++ END OF NEW CODE                                               +++
// +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++


// --- Read configured secret from environment (from .env or server env) ---
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
$adminId = getenv('TELEGRAM_ADMIN_ID') ?: null;
$lotteryChannelId = getenv('LOTTERY_CHANNEL_ID') ?: null;

write_telegram_debug_log("ENV load: TELEGRAM_WEBHOOK_SECRET " . ($expectedSecret ? '[SET]' : '[NOT SET]') . ", TELEGRAM_ADMIN_ID=" . ($adminId ?: '[NOT SET]') . ", LOTTERY_CHANNEL_ID=" . ($lotteryChannelId ?: '[NOT SET]'));

// --- Extract incoming secret token (header preferred, fallback to GET/POST param) ---
$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
if (empty($receivedHeader) && function_exists('getallheaders')) {
    $all = getallheaders();
    foreach ($all as $k => $v) {
        if (strtolower($k) === 'x-telegram-bot-api-secret-token') {
            $receivedHeader = $v;
            break;
        }
    }
}
$receivedParam = $_GET['secret'] ?? ($_POST['secret'] ?? null);
$received = $receivedHeader ?? $receivedParam ?? '';

// Validate secret
if ($expectedSecret) {
    if (empty($received)) {
        write_telegram_debug_log("Webhook rejected: missing secret. Header? " . ($receivedHeader ? 'yes' : 'no') . ", Param? " . ($receivedParam ? 'yes' : 'no'));
        http_response_code(403);
        echo 'Forbidden: Missing secret token.';
        exit();
    }
    if (!hash_equals($expectedSecret, $received)) {
        write_telegram_debug_log("Webhook rejected: secret mismatch. Received preview: " . substr($received,0,12) . "...");
        http_response_code(403);
        echo 'Forbidden: Secret token mismatch.';
        exit();
    }
} else {
    write_telegram_debug_log("WARNING: TELEGRAM_WEBHOOK_SECRET is not configured. Accepting webhook without secret validation (not recommended).");
}

// At this point the webhook is authenticated/allowed. Continue to include helpers.
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/env_manager.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/api_curl_helper.php';
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';

// --- Read request body and parse JSON ---
$bodyRaw = file_get_contents('php://input');
$update = json_decode($bodyRaw, true);

// Log payload (truncated)
write_telegram_debug_log("Webhook payload length=" . strlen($bodyRaw) . ", preview=" . substr($bodyRaw, 0, 1000));

// If invalid JSON, respond 200 (Telegram expects 200) and exit.
if (!is_array($update)) {
    write_telegram_debug_log("Invalid JSON payload received; ignoring.");
    http_response_code(200);
    exit();
}

// Parse update for common types
$chatId = null;
$userId = null;
$commandOrText = null;

if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $chatId = $cb['message']['chat']['id'] ?? null;
    $userId = $cb['from']['id'] ?? null;
    $commandOrText = $cb['data'] ?? null;
    if (!empty($cb['id'])) {
        answerTelegramCallbackQuery($cb['id']);
    }
    write_telegram_debug_log("Received callback_query chat={$chatId} user={$userId} data=" . substr($commandOrText ?? '',0,200));
} elseif (isset($update['message'])) {
    $msg = $update['message'];
    $chatId = $msg['chat']['id'] ?? null;
    $userId = $msg['from']['id'] ?? $chatId;
    $commandOrText = trim($msg['text'] ?? '');
    write_telegram_debug_log("Received message chat={$chatId} user={$userId} text=" . substr($commandOrText ?? '',0,200));

    // +++ REPLACEMENT LOTTERY LOGIC +++
    // If message originated from configured lottery channel, handle specially
    if (!empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
        // We now have a robust, dedicated function to handle this.
        handleLotteryMessage($chatId, $commandOrText);
        
        // Respond to Telegram and exit script.
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'processed lottery message']);
        exit();
    }

} else {
    write_telegram_debug_log("Unsupported update type; ignoring.");
    http_response_code(200);
    exit();
}

// Ensure we have chatId/userId
if (empty($chatId) || empty($userId)) {
    write_telegram_debug_log("Missing chatId or userId after parsing update. chatId=" . var_export($chatId,true));
    http_response_code(200);
    exit();
}

// Admin-only interactive commands: restrict to TELEGRAM_ADMIN_ID if set
if (!empty($adminId) && ((string)$chatId !== (string)$adminId)) {
    write_telegram_debug_log("Unauthorized access from chat {$chatId}; admin required ({$adminId}). Notifying sender.");
    @sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    http_response_code(200);
    exit();
}

// Process commands/state
try {
    // Process stateful interaction first
    $userState = getUserState($userId);
    if ($userState) {
        write_telegram_debug_log("Processing stateful interaction for user {$userId} state={$userState}");
        if (strpos($userState, 'awaiting_api_key_') === 0) {
            $keyToUpdate = substr($userState, strlen('awaiting_api_key_'));
            if (update_env_file($keyToUpdate, $commandOrText)) {
                sendTelegramMessage($chatId, "✅ API 密钥 {$keyToUpdate} 已成功更新！", getAdminKeyboard());
            } else {
                sendTelegramMessage($chatId, "❌ 更新 API 密钥失败！请确保服务器上的 .env 可写。", getAdminKeyboard());
            }
            setUserState($userId, null);
        } elseif ($userState === 'awaiting_gemini_prompt' || $userState === 'awaiting_cloudflare_prompt') {
            sendTelegramMessage($chatId, "🧠 正在处理，请稍候...");
            $response = ($userState === 'awaiting_gemini_prompt') ? call_gemini_api($commandOrText) : call_cloudflare_ai_api($commandOrText);
            sendTelegramMessage($chatId, $response, getAdminKeyboard());
            setUserState($userId, null);
        } elseif ($userState === 'awaiting_user_deletion') {
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
        } else {
            setUserState($userId, null);
            sendTelegramMessage($chatId, "系统状态异常，已重置。", getAdminKeyboard());
        }
        http_response_code(200);
        exit();
    }

    // No state, handle simple commands
    $cmd = strtolower(trim($commandOrText ?? ''));
    $reply = null;
    $replyKeyboard = null;

    switch ($cmd) {
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
                if (!in_array($f, $blacklist, true)) $text .= $f . "\n";
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
                    $text .= "📧 {$u['email']} (注册于: {$u['created_at']})\n";
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
            $stateTo = ($cmd === 'ask_gemini') ? 'awaiting_gemini_prompt' : 'awaiting_cloudflare_prompt';
            setUserState($userId, $stateTo);
            $reply = "好的，请输入您的请求内容：";
            break;
        default:
            if (!empty($cmd)) {
                $reply = "无法识别的命令 '{$commandOrText}'。请使用下方菜单。";
                $replyKeyboard = getAdminKeyboard();
            }
            break;
    }

    if ($reply) {
        sendTelegramMessage($chatId, $reply, $replyKeyboard);
    }

} catch (Throwable $e) {
    write_telegram_debug_log("Exception in webhook processing: " . $e->getMessage());
    if (!empty($adminId)) {
        @sendTelegramMessage($adminId, "Webhook internal error: " . substr($e->getMessage(), 0, 200));
    }
    http_response_code(200);
    exit();
}

// Respond OK
http_response_code(200);
echo json_encode(['status' => 'ok']);
exit();

?>
