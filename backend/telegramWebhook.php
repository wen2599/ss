<?php
// backend/telegramWebhook.php
// Main handler for all incoming Telegram updates.

// --- Bootstrap --- //
require_once __DIR__ . '/config.php'; // Includes all helpers and env vars.

// --- Webhook Data Processing --- //
$rawBody = file_get_contents('php://input');
$update = json_decode($rawBody, true);

// Immediately log the raw update to the new telegram_debug.log
write_telegram_debug_log("------ New Webhook Update Received ------");
write_telegram_debug_log("Raw Body: " . $rawBody);

if (!$update) {
    write_telegram_debug_log("Received invalid JSON or empty update. Exiting.");
    http_response_code(200); // Respond 200 to Telegram to prevent retries.
    exit;
}

// --- Variable Extraction --- //
$message = $update['message'] ?? $update['channel_post'] ?? null;
$callbackQuery = $update['callback_query'] ?? null;
$isCallback = false;
$isChannelPost = isset($update['channel_post']);

$user = null;
$chatId = null;
$userId = null;
$commandOrText = null;

if ($callbackQuery) {
    // Admin interacting with an inline keyboard button
    $user = $callbackQuery['from'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $userId = $user['id'];
    $commandOrText = $callbackQuery['data'];
    $isCallback = true;
    answerTelegramCallbackQuery($callbackQuery['id']); // Acknowledge callback
} elseif ($message) {
    // Admin sending a command OR a new post in the channel
    $user = $message['from'] ?? null; // Channel posts don't have a 'from' user
    $chatId = $message['chat']['id'];
    $userId = $user['id'] ?? $chatId; // For channel, we can use chatId as a unique identifier if needed
    $commandOrText = $message['text'] ?? '';
}

if (!$chatId) {
    write_telegram_debug_log("Could not extract chat_id from the update. Exiting.");
    http_response_code(200);
    exit;
}

// --- Environment Variable Checks --- //
$adminId = getenv('TELEGRAM_ADMIN_ID');
$lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');

if (!$adminId || !$lotteryChannelId) {
    $errorMsg = "CRITICAL: TELEGRAM_ADMIN_ID or LOTTERY_CHANNEL_ID is not set in environment.";
    error_log($errorMsg);
    write_telegram_debug_log($errorMsg);
    // We don't send a message here as we might not know who to send it to.
    http_response_code(200);
    exit;
}

// --- ROUTING LOGIC --- //

try {
    // Route 1: It's a message from the designated lottery channel.
    if ($isChannelPost && (string)$chatId === (string)$lotteryChannelId) {
        write_telegram_debug_log("Update identified as a channel post from the lottery channel. Routing to handleLotteryMessage.");
        handleLotteryMessage($commandOrText); // Process the lottery result
    
    // Route 2: It's an interaction (command or callback) from the Admin.
    } elseif ((string)$userId === (string)$adminId) {
        write_telegram_debug_log("Update identified as an interaction from the admin. Routing to command processor.");
        
        $conn = get_db_connection();
        if (is_array($conn) && isset($conn['db_error'])) {
            $dbError = "Database connection failed: " . $conn['db_error'];
            error_log($dbError);
            write_telegram_debug_log($dbError);
            sendTelegramMessage($chatId, "❌ Database connection is currently unavailable. Please try again later.");
            http_response_code(200);
            exit;
        }

        $userState = getUserState($userId);
        if ($userState) {
            handleStatefulInteraction($conn, $userId, $chatId, $commandOrText, $userState);
        } else {
            processCommand($conn, $userId, $chatId, $commandOrText, $isCallback);
        }

    // Route 3: It's an unauthorized interaction.
    } else {
        $logMsg = "Unauthorized access attempt. User ID: {$userId}, Chat ID: {$chatId}. Expected Admin ID: {$adminId}.";
        error_log($logMsg);
        write_telegram_debug_log($logMsg);
        // We can't send a message if it was a channel post from a random channel.
        if (!$isChannelPost) {
            sendTelegramMessage($chatId, "⛔️ Access Denied. You are not authorized to use this bot.");
        }
    }

} catch (Exception $e) {
    $errorLog = "Exception in webhook router: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile();
    error_log($errorLog);
    write_telegram_debug_log($errorLog);
    // Send a generic error message to the admin if the error originated from an admin interaction.
    if ((string)$userId === (string)$adminId) {
        sendTelegramMessage($chatId, "⚠️ An unexpected error occurred. Please check the server logs.");
    }
}

// --- Final Acknowledgment --- //
write_telegram_debug_log("------ Webhook Processing Finished ------");
http_response_code(200);
echo 'ok';

?>