<?php

// API handler for incoming Telegram Bot Webhooks, writing to the database.
// Core dependencies are now loaded by the main index.php router.

// --- Configuration & Security ---
$secretToken = TELEGRAM_WEBHOOK_SECRET;
$allowedChannelId = TELEGRAM_CHANNEL_ID;
$secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

// --- Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json(['error' => 'Method Not Allowed'], 405);
}

if (!$secretToken || $secretToken !== $secretHeader) {
    error_log('Invalid webhook secret token attempt.');
    Response::json(['error' => 'Unauthorized'], 401);
}

// --- Process Request ---
$update = $GLOBALS['requestBody'] ?? null;

if (!$update) {
    Response::json(['error' => 'No data received'], 400);
}

// --- Extract, Validate, and Store Message ---
if (isset($update['channel_post'])) {
    $channelPost = $update['channel_post'];
    $channelId = $channelPost['chat']['id'] ?? null;
    $messageText = trim($channelPost['text'] ?? '');

    // Validate Channel ID
    if (!$allowedChannelId || $channelId != $allowedChannelId) {
        error_log("Message from unauthorized channel ID: {$channelId}. Allowed: {$allowedChannelId}");
        Response::json(['error' => 'Forbidden: Message from wrong channel'], 403);
    }

    // --- Parse Message and Insert into Database ---
    if (!empty($messageText)) {
        // Expected format: "issue_number winning_numbers"
        // Example: "20231026-001 5,12,18,22,35"
        $parts = preg_split('/\s+/', $messageText, 2);

        if (count($parts) === 2) {
            $issueNumber = $parts[0];
            $winningNumbers = $parts[1];
            $drawingDate = date('Y-m-d');

            try {
                $conn = getDbConnection();

                $stmt = $conn->prepare(
                    "INSERT INTO lottery_numbers (issue_number, winning_numbers, drawing_date) VALUES (?, ?, ?)"
                );

                if ($stmt === false) {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }

                $stmt->bind_param("sss", $issueNumber, $winningNumbers, $drawingDate);

                if ($stmt->execute()) {
                    Response::json(['status' => 'success', 'message' => 'Lottery number saved to database.']);
                } else {
                    throw new Exception("Failed to execute statement: " . $stmt->error);
                }

                $stmt->close();
                $conn->close();

            } catch (Exception $e) {
                // The global exception handler will catch this
                throw new Exception('Database error in telegramWebhook: ' . $e->getMessage());
            }
        } else {
            error_log("Invalid message format received: '{$messageText}'");
            Response::json(['error' => 'Invalid message format'], 422); // 422 Unprocessable Entity
        }
    } else {
        Response::json(['status' => 'ok', 'message' => 'Empty message, ignored']);
    }

} else {
    Response::json(['status' => 'ok', 'message' => 'Payload not a channel post, ignored']);
}