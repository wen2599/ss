<?php

// This script is now a clean API handler.
// All bootstrapping (config, db, etc.) is handled by the main index.php,
// which already required `config.php`.

// --- Main Logic ---
// The request body is already parsed and available in $GLOBALS['requestBody']
// from the bootstrap process, so we can use it directly.
$update = $GLOBALS['requestBody'];

// --- Basic Validation ---
if (!$update) {
    // This can happen if Telegram sends an empty POST request to check the webhook.
    // We can safely exit without an error.
    exit;
}

// --- Routing ---
// Route the update to the appropriate handler based on its content.
try {
    if (isset($update['channel_post'])) {
        handleChannelPost($update['channel_post']);
        exit;
    }

    if (isset($update['message'])) {
        handleUserMessage($update['message']);
        exit;
    }

    // If neither key is present, there's nothing to do.
    error_log("Webhook received an update with no 'channel_post' or 'message' key.");

} catch (Throwable $e) {
    // The global exception handler in config.php will catch this,
    // log it, and send a 500 response.
    throw $e;
}

// --- Function Definitions ---
// These functions are called by the routing logic above.

function handleChannelPost(array $post): void
{
    // Placeholder for channel post handling logic
    // For now, we just log it.
    error_log("Received channel post: " . json_encode($post));
}

function handleUserMessage(array $message): void
{
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';

    // Simple echo bot for demonstration
    sendMessage($chatId, "You said: " . htmlspecialchars($text));
}