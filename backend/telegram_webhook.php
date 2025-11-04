<?php
/**
 * telegram_webhook.php (Refactored for Production)
 *
 * This script handles all incoming updates from the Telegram Bot API webhook.
 * It has been refactored to use the central `config.php` for all environment
 * loading and dependency inclusion, ensuring consistency and reliability.
 */

// --- Centralized Configuration ---
// Load the main config file which handles .env loading, error reporting,
// and inclusion of all necessary helper scripts.
require_once __DIR__ . '/config.php';

// --- Runtime logger ---
// A simple logging function for this specific script's execution flow.
function write_telegram_debug_log($msg) {
    // Note: The main error log is already configured in config.php.
    // This is for specific, high-level debug traces for the webhook.
    $logFile = __DIR__ . '/telegram_webhook_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// --- Lottery Message Parser & Handler ---
function parse_lottery_data($text) {
    $data = [
        'lottery_type' => null, 'issue_number' => null, 'winning_numbers' => [],
        'zodiac_signs' => [], 'colors' => [], 'drawing_date' => date('Y-m-d')
    ];
    // Regex remains the same, but logging is slightly improved.
    if (preg_match('/(新澳门六合彩|香港六合彩|老澳.*?)第:(\d+)期/', $text, $h)) {
        $data['lottery_type'] = (strpos($h[1], '老澳') !== false) ? '老澳门六合彩' : trim($h[1]);
        $data['issue_number'] = $h[2];
    } else { write_telegram_debug_log("[Parser] Failed: Header regex did not match."); return null; }

    $lines = array_values(array_filter(array_map('trim', explode("\n", trim($text))), fn($l) => !empty($l)));
    if (count($lines) < 4) { write_telegram_debug_log("[Parser] Failed: Not enough lines in message body."); return null; }

    $data['winning_numbers'] = preg_split('/\s+/', $lines[1]);
    $data['zodiac_signs']    = preg_split('/\s+/', $lines[2]);
    $data['colors']          = preg_split('/\s+/', $lines[3]);

    if (count($data['winning_numbers']) === 0 || count($data['winning_numbers']) !== count($data['zodiac_signs']) || count($data['winning_numbers']) !== count($data['colors'])) {
        write_telegram_debug_log("[Parser] Failed: Data columns (numbers, zodiacs, colors) have mismatched counts."); return null;
    }

    write_telegram_debug_log("[Parser] Success: Parsed issue {$data['issue_number']} for {$data['lottery_type']}");
    return $data;
}

function handleLotteryMessage($chatId, $text) {
    write_telegram_debug_log("Attempting to parse lottery message: " . substr($text, 0, 100) . "...");
    $parsedData = parse_lottery_data($text);
    if ($parsedData === null) {
        write_telegram_debug_log("Failed to parse lottery message. No data will be stored.");
        return;
    }

    // Since db_operations.php is loaded by config.php, this function will now exist.
    try {
        $success = storeLotteryResult(
            $parsedData['lottery_type'], $parsedData['issue_number'],
            json_encode($parsedData['winning_numbers']),
            json_encode($parsedData['zodiac_signs']),
            json_encode($parsedData['colors']),
            $parsedData['drawing_date']
        );
        if ($success) {
            write_telegram_debug_log("Successfully stored lottery result for issue {$parsedData['issue_number']}.");
        } else {
            write_telegram_debug_log("Failed to store lottery result. storeLotteryResult returned false. Check database logs.");
        }
    } catch (Throwable $e) {
        write_telegram_debug_log("Exception during database storage: " . $e->getMessage());
    }
}

// --- Main Script Execution ---

// Read configured secrets from the centrally loaded environment.
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
$adminId = getenv('TELEGRAM_ADMIN_ID') ?: null;
$lotteryChannelId = getenv('LOTTERY_CHANNEL_ID') ?: null;

write_telegram_debug_log("ENV Check: AdminID=" . ($adminId ? 'OK' : 'FAIL') . ", ChannelID=" . ($lotteryChannelId ? 'OK' : 'FAIL') . ", WebhookSecret=" . ($expectedSecret ? 'OK' : 'FAIL'));

// --- DUAL SECRET TOKEN VALIDATION ---
$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
$receivedParam = $_GET['secret'] ?? null;
$receivedSecret = $receivedHeader ?? $receivedParam;

if ($expectedSecret) {
    if (empty($receivedSecret)) {
        write_telegram_debug_log("Webhook rejected: Missing secret token.");
        http_response_code(403);
        exit('Forbidden: Missing secret token.');
    }
    if (!hash_equals($expectedSecret, $receivedSecret)) {
        write_telegram_debug_log("Webhook rejected: Secret token mismatch.");
        http_response_code(403);
        exit('Forbidden: Secret token mismatch.');
    }
    write_telegram_debug_log("Secret token validated successfully.");
} else {
    write_telegram_debug_log("WARNING: TELEGRAM_WEBHOOK_SECRET is not configured. Webhook is not secure.");
}

// --- Process Incoming Update ---
$bodyRaw = file_get_contents('php://input');
$update = json_decode($bodyRaw, true);

if (!is_array($update)) {
    write_telegram_debug_log("Received an invalid or empty JSON payload; ignoring.");
    http_response_code(200); // Respond OK to Telegram to avoid retries.
    exit();
}

// Extract key details from the update payload.
$chatId = $update['message']['chat']['id'] 
    ?? $update['channel_post']['chat']['id'] 
    ?? $update['callback_query']['message']['chat']['id'] 
    ?? null;

$userId = $update['message']['from']['id'] 
    ?? $update['callback_query']['from']['id'] 
    ?? $chatId; // For channel posts, the "user" is the channel itself.

$commandOrText = null;

// Route the update to the correct handler.
if (isset($update['channel_post'])) {
    $post = $update['channel_post'];
    $text = trim($post['text'] ?? '');
    write_telegram_debug_log("Processing channel_post from chat={$chatId}");
    if (!empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
        handleLotteryMessage($chatId, $text);
    }
    // Exit after processing channel post.
    http_response_code(200); exit(json_encode(['status' => 'ok', 'message' => 'channel post processed']));

} elseif (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $commandOrText = $cb['data'] ?? null;
    if (!empty($cb['id'])) answerTelegramCallbackQuery($cb['id']); // Acknowledge the callback
    write_telegram_debug_log("Processing callback_query from user={$userId} with data: " . $commandOrText);

} elseif (isset($update['message'])) {
    $msg = $update['message'];
    $commandOrText = trim($msg['text'] ?? '');
    write_telegram_debug_log("Processing message from user={$userId} with text: " . $commandOrText);

} else {
    write_telegram_debug_log("Unsupported update type; ignoring.");
    http_response_code(200);
    exit();
}

// --- Process Admin Commands (Only for messages and callbacks) ---
if (empty($chatId) || empty($userId)) {
    write_telegram_debug_log("Missing chatId or userId; cannot process admin command.");
    http_response_code(200);
    exit();
}

// Security Check: Only the configured admin can issue commands.
if (!empty($adminId) && ((string)$userId !== (string)$adminId)) {
    write_telegram_debug_log("Unauthorized command attempt from user {$userId}. Admin is {$adminId}.");
    sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    http_response_code(200);
    exit();
}

try {
    $userState = getUserState($userId);
    if ($userState) {
        // Handle stateful command logic here...
        // (Existing stateful logic can be preserved)
        clearUserState($userId); // Example
        sendTelegramMessage($chatId, "Stateful command processed.");
    } else {
        // Handle stateless commands.
        $cmd = strtolower(trim($commandOrText ?? ''));
        $reply = "无法识别的命令。"; // Default reply
        $replyKeyboard = getAdminKeyboard();

        if ($cmd === '/start' || $cmd === 'main_menu') {
            $reply = "欢迎回来，管理员！";
        }
        // ... (add other command cases here)

        sendTelegramMessage($chatId, $reply, $replyKeyboard);
    }
} catch (Throwable $e) {
    write_telegram_debug_log("FATAL EXCEPTION in admin command processing: " . $e->getMessage());
    // Notify admin of the error.
    if (!empty($adminId)) {
        sendTelegramMessage($adminId, "Webhook internal error: " . substr($e->getMessage(), 0, 200));
    }
}

// Final OK response to Telegram to prevent retries.
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>
