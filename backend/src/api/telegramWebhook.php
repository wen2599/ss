<?php

// API handler for incoming Telegram Bot Webhooks.
// This version ONLY handles channel posts to store lottery numbers.
// Core dependencies are loaded by the main index.php router.

// --- Security Check ---
// Ensure this script is loaded by index.php, not accessed directly.
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// --- Main Logic ---
$update = $GLOBALS['requestBody'] ?? null;
if (!$update) {
    // If no update, do nothing. Telegram might send empty requests to check the hook.
    exit;
}

// We only care about channel posts. Ignore all other update types (like direct messages).
if (isset($update['channel_post'])) {
    $post = $update['channel_post'];

    // Security: Check if the post is from the allowed channel
    if ($post['chat']['id'] != TELEGRAM_CHANNEL_ID) {
        error_log("Ignoring post from unauthorized channel: " . $post['chat']['id']);
        exit;
    }

    $messageText = trim($post['text'] ?? '');
    if (empty($messageText)) {
        exit;
    }

    // Expected format: "issue_number winning_numbers"
    $parts = preg_split('/\s+/', $messageText, 2);
    if (count($parts) === 2) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("INSERT INTO lottery_numbers (issue_number, winning_numbers, drawing_date) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $parts[0], $parts[1], date('Y-m-d'));
            $stmt->execute();
            $stmt->close();
            $conn->close();
            error_log("Successfully saved lottery number for issue: " . $parts[0]);
        } catch (Exception $e) {
            // The global exception handler will catch and log this.
            throw new Exception("Failed to save lottery number: " . $e->getMessage());
        }
    } else {
        error_log("Invalid message format received from channel: '{$messageText}'");
    }
}

// Acknowledge receipt to Telegram to prevent retries
http_response_code(200);
echo json_encode(['status' => 'ok']);
exit;