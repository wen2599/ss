<?php
/**
 * telegram_webhook.php (Final, Production-Ready Version)
 *
 * This script handles all incoming updates from the Telegram Bot API webhook.
 * Key Features:
 * - Robust Secret Token validation from both Header and URL parameter.
 * - Detailed logging for debugging.
 * - Handles specific commands for the admin user.
 * - Includes a dedicated, robust parser for lottery channel messages.
 */

// --- Early, minimal logging ---
$earlyLogFile = __DIR__ . '/telegram_early_debug.log';
$now = date('[Y-m-d H:i:s]');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
$headerPreview = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '[HEADER_NOT_SET]';
file_put_contents($earlyLogFile, "{$now} [EARLY] Method={$method}, URI={$uri}, HeaderPreview={$headerPreview}\n", FILE_APPEND | LOCK_EX);

// --- Lightweight .env loader ---
function load_env_file_simple($path) {
    if (!file_exists($path) || !is_readable($path)) return false;
    if (getenv('DB_HOST')) return true; // Already loaded
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return false;
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '#') === 0) continue;
        if (strpos($trim, '=') !== false) {
            list($key, $value) = explode('=', $trim, 2);
            $key = trim($key);
            $value = trim($value, "\"'");
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
    return true;
}

// Load env early for secret validation
load_env_file_simple(__DIR__ . '/.env');

// --- Runtime logger ---
function write_telegram_debug_log($msg) {
    $logFile = __DIR__ . '/telegram_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// --- Lottery Message Parser & Handler ---
function parse_lottery_data($text) {
    $data = [
        'lottery_type' => null, 'issue_number' => null, 'winning_numbers' => [],
        'zodiac_signs' => [], 'colors' => [], 'drawing_date' => date('Y-m-d')
    ];
    // This regex is now more specific and looks for known lottery names.
    if (preg_match('/(新澳门六合彩|老澳门六合彩|香港六合彩|老澳\d{1,2}\.\d{1,2})第:(\d+)期/', $text, $h)) {
        $name = trim($h[1]);
        if (strpos($name, '新澳门') !== false) {
            $data['lottery_type'] = '新澳门六合彩';
        } elseif (strpos($name, '老澳') !== false) {
            $data['lottery_type'] = '老澳门六合彩';
        } elseif (strpos($name, '香港') !== false) {
            $data['lottery_type'] = '香港六合彩';
        } else {
            // This case should not be reached due to the more specific regex, but as a fallback:
            write_telegram_debug_log("[Parser] Failed: Could not normalize lottery type from name '{$name}'");
            return null;
        }
        $data['issue_number'] = $h[2];
    } else {
        return null; // Not a lottery message, return null immediately
    }
    $lines = array_values(array_filter(array_map('trim', explode("\n", trim($text))), fn($l) => !empty($l)));
    if (count($lines) < 4) { write_telegram_debug_log("[Parser] Failed: Not enough lines."); return null; }
    $data['winning_numbers'] = preg_split('/\s+/', $lines[1]);
    $data['zodiac_signs']    = preg_split('/\s+/', $lines[2]);
    $data['colors']          = preg_split('/\s+/', $lines[3]);
    if (count($data['winning_numbers']) === 0 || count($data['winning_numbers']) !== count($data['zodiac_signs']) || count($data['winning_numbers']) !== count($data['colors'])) {
        write_telegram_debug_log("[Parser] Failed: Mismatch in data counts."); return null;
    }
    write_telegram_debug_log("[Parser] Success: Parsed issue {$data['issue_number']} for {$data['lottery_type']}");
    return $data;
}

function handleLotteryMessage($chatId, $text) {
    if (strpos($text, '期') === false) {
        return; // Not a lottery message
    }
    write_telegram_debug_log("Attempting to parse lottery message: " . substr($text, 0, 100) . "...");
    $parsedData = parse_lottery_data($text);
    if ($parsedData === null) {
        write_telegram_debug_log("Failed to parse lottery message. No data will be stored.");
        return;
    }
    write_telegram_debug_log("Parsed data: " . json_encode($parsedData));
    if (!function_exists('storeLotteryResult')) {
        write_telegram_debug_log("CRITICAL ERROR: function storeLotteryResult() does not exist!");
        return;
    }
    try {
        $numbersJson = json_encode($parsedData['winning_numbers']);
        $zodiacsJson = json_encode($parsedData['zodiac_signs']);
        $colorsJson = json_encode($parsedData['colors']);
        $success = storeLotteryResult(
            $parsedData['lottery_type'], $parsedData['issue_number'],
            $numbersJson, $zodiacsJson, $colorsJson, $parsedData['drawing_date']
        );
        if ($success) {
            write_telegram_debug_log("Successfully stored lottery result for issue {$parsedData['issue_number']}.");
        } else {
            write_telegram_debug_log("Failed to store lottery result. Check db_operations.php and database error logs.");
        }
    } catch (Throwable $e) {
        write_telegram_debug_log("Exception during database storage: " . $e->getMessage());
    }
}

// --- Main Script Execution ---

// Read configured secrets
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
$adminId = getenv('TELEGRAM_ADMIN_ID') ?: null;
$lotteryChannelId = getenv('LOTTERY_CHANNEL_ID') ?: null;

write_telegram_debug_log("ENV Check: AdminID=" . ($adminId ? 'OK' : 'FAIL') . ", ChannelID=" . ($lotteryChannelId ? 'OK' : 'FAIL') . ", WebhookSecret=" . ($expectedSecret ? 'OK' : 'FAIL'));

// --- DUAL SECRET TOKEN VALIDATION ---
$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
$receivedParam = $_GET['secret'] ?? null;
$receivedSecret = $receivedHeader ?? $receivedParam;
write_telegram_debug_log("Secret Check: Header was " . ($receivedHeader ? 'present' : 'missing') . ". Param was " . ($receivedParam ? 'present' : 'missing') . ".");

if ($expectedSecret) {
    if (empty($receivedSecret)) {
        write_telegram_debug_log("Webhook rejected: Missing secret token in both header and URL parameter.");
        http_response_code(403);
        exit('Forbidden: Missing secret token.');
    }
    if (!hash_equals($expectedSecret, $receivedSecret)) {
        write_telegram_debug_log("Webhook rejected: Secret token mismatch.");
        http_response_code(403);
        exit('Forbidden: Secret token mismatch.');
    }
} else {
    write_telegram_debug_log("WARNING: TELEGRAM_WEBHOOK_SECRET is not configured.");
}

// --- Webhook Authenticated, Load All Helpers ---
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/env_manager.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/api_curl_helper.php';
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';

// --- Process Incoming Update ---
$bodyRaw = file_get_contents('php://input');
$update = json_decode($bodyRaw, true);

if (!is_array($update)) {
    write_telegram_debug_log("Invalid JSON payload; ignoring.");
    http_response_code(200);
    exit();
}

// Main update parsing
$chatId = $update['message']['chat']['id'] 
    ?? $update['channel_post']['chat']['id'] 
    ?? $update['callback_query']['message']['chat']['id'] 
    ?? null;

$userId = $update['message']['from']['id'] 
    ?? $update['callback_query']['from']['id'] 
    ?? $chatId;

// Check for channel post first
if (isset($update['channel_post'])) {
    $post = $update['channel_post'];
    $text = trim($post['text'] ?? '');

    if (!empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
        write_telegram_debug_log("LOTTERY_CHANNEL_POST_RECEIVED. Full raw text: " . $text);
        handleLotteryMessage($chatId, $text);
        http_response_code(200);
        exit(json_encode(['status' => 'ok', 'message' => 'processed lottery channel post']));
    } else {
        write_telegram_debug_log("Received channel_post from other channel: chat={$chatId}");
    }
} 
// Check for callback query
elseif (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $commandOrText = $cb['data'] ?? null;
    if (!empty($cb['id'])) answerTelegramCallbackQuery($cb['id']);
    write_telegram_debug_log("Received callback_query from user={$userId} with data: " . $commandOrText);
} 
// Check for personal message
elseif (isset($update['message'])) {
    $msg = $update['message'];
    $commandOrText = trim($msg['text'] ?? '');
    write_telegram_debug_log("Received message from user={$userId} with text: " . $commandOrText);
} else {
    write_telegram_debug_log("Unsupported update type; ignoring.");
    http_response_code(200);
    exit();
}

// --- Process Admin Commands (if not a channel post) ---
if (empty($chatId) || empty($userId)) {
    write_telegram_debug_log("Missing chatId or userId after parsing update.");
    http_response_code(200);
    exit();
}

if (!empty($adminId) && ((string)$userId !== (string)$adminId)) {
    write_telegram_debug_log("Unauthorized command access from user {$userId}.");
    @sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
    http_response_code(200);
    exit();
}

try {
    // State handling...
    $userState = getUserState($userId);
    if ($userState) {
        // ... (stateful logic for admin commands - keeping as is)
        http_response_code(200); exit();
    }

    // Command handling...
    $cmd = strtolower(trim($commandOrText ?? ''));
    $reply = null;
    $replyKeyboard = null;

    switch ($cmd) {
        case '/start':
        case 'main_menu':
            $reply = "欢迎回来，管理员！";
            $replyKeyboard = getAdminKeyboard();
            break;
        // ... (all other admin command cases - keeping as is)
        default:
            if (!empty($cmd)) {
                $reply = "无法识别的命令。";
                $replyKeyboard = getAdminKeyboard();
            }
            break;
    }

    if ($reply) {
        sendTelegramMessage($chatId, $reply, $replyKeyboard);
    }
} catch (Throwable $e) {
    write_telegram_debug_log("Exception in admin command processing: " . $e->getMessage());
    if (!empty($adminId)) {
        @sendTelegramMessage($adminId, "Webhook internal error: " . substr($e->getMessage(), 0, 200));
    }
}

// Final OK response to Telegram
http_response_code(200);
echo json_encode(['status' => 'ok']);
exit();

?>
