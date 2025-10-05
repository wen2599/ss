<?php
// backend/endpoints/tg_webhook.php

// --- Security Check: Ensure required constants are defined ---
if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_ADMIN_ID')) {
    error_log("Telegram bot token or admin ID is not configured.");
    exit;
}

// 1. Get the incoming update from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (!$update || !isset($update['message']['text']) || !isset($update['message']['from']['id'])) {
    exit; // Ignore non-message updates.
}

$message = $update['message'];
$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$text = trim($message['text']);

// 2. --- Admin-Only Authorization ---
// Only process commands from the configured admin user.
if ((string)$user_id !== (string)TELEGRAM_ADMIN_ID) {
    // Silently ignore messages from non-admins.
    exit;
}

// 3. --- Command Parsing and Handling ---
if (strpos($text, '/add_email') === 0) {
    $parts = explode(' ', $text, 2);
    $email_to_add = trim($parts[1] ?? '');

    if (empty($email_to_add) || !filter_var($email_to_add, FILTER_VALIDATE_EMAIL)) {
        send_telegram_message($chat_id, "❌ Invalid format. Please use: `/add_email user@example.com`");
        exit;
    }

    $conn = get_db_connection();
    if (!$conn) {
        send_telegram_message($chat_id, "🚨 *Error:* Could not connect to the database. Please check the server logs.");
        exit;
    }

    // Check for duplicates
    $stmt_check = $conn->prepare("SELECT email FROM allowed_emails WHERE email = ?");
    $stmt_check->bind_param("s", $email_to_add);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        send_telegram_message($chat_id, "⚠️ This email `{$email_to_add}` is already on the allowed list.");
        $stmt_check->close();
        $conn->close();
        exit;
    }
    $stmt_check->close();

    // Insert the new email
    $stmt_insert = $conn->prepare("INSERT INTO allowed_emails (email) VALUES (?)");
    $stmt_insert->bind_param("s", $email_to_add);

    if ($stmt_insert->execute()) {
        send_telegram_message($chat_id, "✅ *Success!* The email `{$email_to_add}` has been added to the allowed list.");
    } else {
        error_log("Failed to insert allowed email: " . $stmt_insert->error);
        send_telegram_message($chat_id, "🚨 *Error:* Could not add the email to the database.");
    }

    $stmt_insert->close();
    $conn->close();
} else {
    // --- Debugging Catch-All ---
    // If the command is not recognized, send a help message.
    // This confirms the webhook is receiving messages from the admin.
    $help_text = "🤖 Hello Admin! I'm alive.\n\n";
    $help_text .= "To add a user, use the command:\n";
    $help_text .= "`/add_email user@example.com`";
    send_telegram_message($chat_id, $help_text);
}
?>