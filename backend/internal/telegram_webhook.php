<?php
require_once __DIR__ . '/../core/initialize.php';

$update = json_decode(file_get_contents('php://input'), true);

if (!isset($update['message'])) {
    exit(); // Not a message update
}

$chat_id = $update['message']['chat']['id'];
$text = $update['message']['text'];

// Security Check: Only allow admin
if ($chat_id != $_ENV['TELEGRAM_ADMIN_ID']) {
    // You might want to send a reply here, or just silently ignore
    exit();
}

// Command processing
if (strpos($text, '/delete_user') === 0) {
    $parts = explode(' ', $text);
    $email_to_delete = $parts[1] ?? '';
    
    if (filter_var($email_to_delete, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
            $stmt->execute([$email_to_delete]);
            $count = $stmt->rowCount();
            $reply_text = "$count user(s) with email '$email_to_delete' deleted.";
        } catch (PDOException $e) {
            $reply_text = "Database error: " . $e->getMessage();
        }
    } else {
        $reply_text = "Invalid command. Usage: /delete_user user@example.com";
    }
    
    // Reply to Telegram (You'll need a helper function for this)
    $bot_token = $_ENV['TELEGRAM_BOT_TOKEN'];
    file_get_contents("https://api.telegram.org/bot{$bot_token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($reply_text));
}
