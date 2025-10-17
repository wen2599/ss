<?php
// telegramWebhook.php

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
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return false;
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '#') === 0) continue;
        if (strpos($trim, '=') === false) continue;
        list($key, $value) = explode('=', $trim, 2);
        $key = trim($key);
        $value = trim(trim($value, "'\"")); // Trim quotes
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    return true;
}

// Load .env for early access to secrets
$envPath = __DIR__ . '/.env';
load_env_file_simple($envPath);

// --- Runtime logger ---
function write_telegram_debug_log($msg) {
    $logFile = __DIR__ . '/telegram_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s]') . " " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// --- Secret validation ---
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;

if (!$receivedHeader && function_exists('getallheaders')) {
    $all = getallheaders();
    $receivedHeader = $all['X-Telegram-Bot-Api-Secret-Token'] ?? $all['x-telegram-bot-api-secret-token'] ?? null;
}

$receivedParam = $_GET['secret'] ?? $_POST['secret'] ?? null;
$received = $receivedHeader ?? $receivedParam;

if ($expectedSecret) {
    if (!$received || !hash_equals($expectedSecret, $received)) {
        write_telegram_debug_log("Webhook rejected: secret mismatch. Received: " . substr($received ?? '', 0, 8));
        http_response_code(403);
        echo 'Forbidden';
        exit();
    }
} else {
    write_telegram_debug_log("WARNING: TELEGRAM_WEBHOOK_SECRET is not set. Accepting webhook without validation.");
}

// --- Include necessary files AFTER validation ---
// config.php establishes the database connection ($conn)
require_once __DIR__ . '/config.php'; 
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/db_operations.php';
require_once __DIR__ . '/api_curl_helper.php';
require_once __DIR__ . '/gemini_ai_helper.php';
require_once __DIR__ . '/cloudflare_ai_helper.php';

// --- Main logic ---
$bodyRaw = file_get_contents('php://input');
$update = json_decode($bodyRaw, true);
write_telegram_debug_log("Payload preview: " . substr($bodyRaw, 0, 500));

if (!$update) {
    write_telegram_debug_log("Invalid JSON payload.");
    http_response_code(200); // Respond OK to Telegram
    exit();
}

try {
    $adminId = getenv('TELEGRAM_ADMIN_ID') ?: null;
    $lotteryChannelId = getenv('LOTTERY_CHANNEL_ID') ?: null;

    $chatId = null;
    $userId = null;
    $commandOrText = null;
    $isCallback = false;

    if (isset($update['callback_query'])) {
        $cb = $update['callback_query'];
        $chatId = $cb['message']['chat']['id'];
        $userId = $cb['from']['id'];
        $commandOrText = $cb['data'];
        $isCallback = true;
        answerTelegramCallbackQuery($cb['id']); // Acknowledge callback quickly
    } elseif (isset($update['message'])) {
        $msg = $update['message'];
        $chatId = $msg['chat']['id'];
        $userId = $msg['from']['id'];
        $commandOrText = trim($msg['text'] ?? '');
    }

    if (!$chatId || !$userId) {
        write_telegram_debug_log("Missing chatId or userId.");
        exit_with_ok();
    }
    
    // --- Handle Lottery Channel Message ---
    if ($lotteryChannelId && (string)$chatId === (string)$lotteryChannelId) {
        write_telegram_debug_log("Processing message from lottery channel {$lotteryChannelId}.");
        // This assumes handleLotteryMessage is defined elsewhere or inline.
        // For now, we just log and exit. You can add lottery result parsing here.
        exit_with_ok();
    }

    // --- Restrict to Admin ---
    if (!$adminId || (string)$userId !== (string)$adminId) {
        sendTelegramMessage($chatId, "抱歉，您无权使用此机器人。");
        write_telegram_debug_log("Unauthorized access attempt from userId: {$userId}");
        exit_with_ok();
    }

    // --- State Management ---
    $userState = getUserState($userId);
    if ($userState) {
        handleStatefulInteraction($conn, $userId, $chatId, $commandOrText, $userState);
        exit_with_ok();
    }
    
    // --- Command Processing ---
    processCommand($conn, $userId, $chatId, $commandOrText, $isCallback);

} catch (Throwable $e) {
    // Log detailed error and notify admin if possible
    $errorDetails = $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString();
    write_telegram_debug_log("EXCEPTION: " . $errorDetails);
    if (isset($adminId)) {
        @sendTelegramMessage($adminId, "机器人遇到内部错误，请检查 `telegram_debug.log`。");
    }
}

exit_with_ok(); // Final response

function exit_with_ok() {
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit();
}
