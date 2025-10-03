<?php
// This script handles callbacks from the Telegram bot, e.g., for user registration approval.

require_once __DIR__ . '/init.php'; // Provides $pdo, $log, $admin_id, etc.

use App; // Use the App namespace for Telegram, User, etc.

// The global $log is available from init.php
$log->info("--- Telegram Webhook Triggered ---");

// --- 1. Security Check: Validate Telegram Secret Token ---
$secretToken = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (empty($secretToken) || empty($receivedToken) || !hash_equals($secretToken, $receivedToken)) {
    http_response_code(403);
    $log->warning("Unauthorized webhook access attempt.", ['received_token' => $receivedToken]);
    // We don't echo JSON here as the request might not be from a legitimate source.
    exit('Forbidden');
}

// --- 2. Process Incoming Request ---
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    http_response_code(400);
    $log->error("Failed to decode JSON from Telegram update.");
    exit();
}

$log->debug("Received update from Telegram", ['update' => $update]);

// We are only interested in callback queries (button presses)
if (!isset($update['callback_query'])) {
    $log->info("Update is not a callback query, ignoring.");
    exit();
}

$callbackQuery = $update['callback_query'];
$callbackData = $callbackQuery['data'];
$chatId = $callbackQuery['message']['chat']['id'];
$messageId = $callbackQuery['message']['message_id'];
$fromId = $callbackQuery['from']['id'];

// --- 3. Authorization Check ---
// Use the global $admin_id from init.php
if (empty($admin_id) || $fromId != $admin_id) {
    Telegram::answerCallbackQuery($callbackQuery['id'], '您没有权限执行此操作。', true);
    $log->warning("Permission denied for callback query.", ['from_id' => $fromId, 'admin_id' => $admin_id]);
    exit();
}

// --- 4. Handle Registration Callbacks ---
if (strpos($callbackData, 'approve_dbid_') === 0 || strpos($callbackData, 'deny_dbid_') === 0) {
    $parts = explode('_', $callbackData);
    $action = $parts[0];
    $userId = (int) ($parts[2] ?? 0);

    if ($userId === 0) {
        Telegram::answerCallbackQuery($callbackQuery['id'], '无效的用户ID。', true);
        $log->error("Invalid user ID in callback data.", ['data' => $callbackData]);
        exit();
    }

    try {
        $user = User::findById($pdo, $userId);
        if (!$user) {
            throw new Exception("User with ID {$userId} not found.");
        }

        $newStatus = ($action === 'approve') ? 'approved' : 'denied';

        // Update user status in the database
        $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $userId]);

        // Notify admin of the action taken
        $responseText = "用户 " . htmlspecialchars($user['email']) . " 的注册申请已 *{$newStatus}*。";
        Telegram::sendMessage($chatId, $responseText);

        // Acknowledge the callback to stop the loading icon on the button
        Telegram::answerCallbackQuery($callbackQuery['id'], "操作成功！");

        // Remove the inline keyboard from the original message to prevent re-clicks
        Telegram::editMessageReplyMarkup($chatId, $messageId, json_encode(['inline_keyboard' => []]));

        $log->info("User registration status updated.", ['user_id' => $userId, 'status' => $newStatus, 'admin_id' => $fromId]);

    } catch (Throwable $e) {
        $log->error("Error processing registration callback.", [
            'error' => $e->getMessage(),
            'callback_data' => $callbackData
        ]);
        // Inform the admin that something went wrong
        Telegram::answerCallbackQuery($callbackQuery['id'], '处理时发生错误。', true);
    }
} else {
    $log->info("Received unhandled callback data.", ['callback_data' => $callbackData]);
    Telegram::answerCallbackQuery($callbackQuery['id'], '未知的操作。', true);
}

// Acknowledge the webhook request to Telegram
echo json_encode(['success' => true]);
?>