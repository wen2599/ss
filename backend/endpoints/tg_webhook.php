<?php
// backend/endpoints/telegram_webhook.php

// --- Security Check: Ensure required constants are defined ---
if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_ADMIN_ID')) {
    // Log an error and exit silently. No response should be sent to Telegram.
    error_log("Telegram bot token or admin ID is not configured.");
    exit;
}

// 1. Get the incoming update from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (!$update || !isset($update['message']['text']) || !isset($update['message']['from']['id'])) {
    // Not a valid message update, so we can ignore it.
    exit;
}

$message = $update['message'];
$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$text = trim($message['text']);

// 2. --- Admin-Only Authorization ---
// Only process commands from the configured admin user.
if ((string)$user_id !== (string)TELEGRAM_ADMIN_ID) {
    // Optional: could send a "not authorized" message, but it's better to stay silent.
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

    // 4. --- Database Interaction ---
    $conn = get_db_connection();
    if (!$conn) {
        send_telegram_message($chat_id, "🚨 Error: Could not connect to the database.");
        exit;
    }

    // Check if the email already exists to prevent duplicates.
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

    // Insert the new email. Assumes an 'allowed_emails' table with an 'email' column.
    $stmt_insert = $conn->prepare("INSERT INTO allowed_emails (email) VALUES (?)");
    $stmt_insert->bind_param("s", $email_to_add);

    if ($stmt_insert->execute()) {
        send_telegram_message($chat_id, "✅ Success! The email `{$email_to_add}` has been added to the allowed list.");
    } else {
        error_log("Failed to insert allowed email: " . $stmt_insert->error);
        send_telegram_message($chat_id, "🚨 Error: Could not add the email to the database.");
    }

    $stmt_insert->close();
    $conn->close();
}
?>