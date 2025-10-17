<?php
// telegramWebhook.php

// --- Load Config and Essential Helpers ---
// config.php handles robust environment loading, error logging, and includes all other necessary helpers.
require_once __DIR__ . '/config.php';

// Early log for webhook hit, now using the centralized logger.
write_telegram_debug_log(
    sprintf(
        "[WEBHOOK_HIT] Method=%s, URI=%s, SecretHeader=%s",
        $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
        isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) ? 'Present' : 'Not-Set'
    )
);

// --- Runtime logger (uses the main debug log configured in config.php) ---
function write_telegram_debug_log($msg) {
    // This function now acts as a wrapper for the standard error_log.
    // It helps keep logging calls consistent in this file.
    error_log("TELEGRAM_WEBHOOK: " . $msg);
}

// --- Secret validation ---
$expectedSecret = getenv('TELEGRAM_WEBHOOK_SECRET') ?: null;
$receivedHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;

// Fallback for servers that don't populate $_SERVER correctly
if (!$receivedHeader && function_exists('getallheaders')) {
    $all = getallheaders();
    $receivedHeader = $all['X-Telegram-Bot-Api-Secret-Token'] ?? $all['x-telegram-bot-api-secret-token'] ?? null;
}

// Allow secret to be passed as a query parameter as a fallback
$receivedParam = $_GET['secret'] ?? $_POST['secret'] ?? null;

// Prioritize the header for security
$receivedSecret = $receivedHeader ?? $receivedParam;

if ($expectedSecret) {
    if (!$receivedSecret || !hash_equals($expectedSecret, $receivedSecret)) {
        write_telegram_debug_log("Webhook rejected: Secret mismatch. Received: " . substr($receivedSecret ?? '', 0, 8) . "...");
        http_response_code(403); // Forbidden
        echo 'Forbidden: Invalid secret token.';
        exit();
    }
} else {
    // It's good practice to log when running in an insecure mode.
    write_telegram_debug_log("WARNING: TELEGRAM_WEBHOOK_SECRET is not set. Accepting webhook without secret validation.");
}


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
        handleLotteryMessage($chatId, $commandOrText); // Call the helper function
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
        // Pass the database connection getter, not a variable
        handleStatefulInteraction('get_db_connection', $userId, $chatId, $commandOrText, $userState);
        exit_with_ok();
    }
    
    // --- Command Processing ---
    // Pass the database connection getter to the command processor
    processCommand('get_db_connection', $userId, $chatId, $commandOrText, $isCallback);

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
