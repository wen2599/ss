<?php

// --- PING SCRIPT ---
// A minimal script to test if the server can send a Telegram message.

// Set up error logging
ini_set('display_errors', 0);
error_reporting(0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/ping_debug.log'); // Use a separate log file

error_log("--- PING SCRIPT STARTED ---");

try {
    // 1. Load the absolute minimum necessary files
    require_once __DIR__ . '/config.php';
    error_log("config.php loaded.");

    // 2. Get the necessary credentials
    $bot_token = getenv('TELEGRAM_BOT_TOKEN');
    $admin_id = getenv('TELEGRAM_ADMIN_ID');

    if (empty($bot_token) || empty($admin_id)) {
        error_log("CRITICAL: Bot token or admin ID is not set in .env file.");
        exit();
    }
    error_log("Credentials loaded successfully.");

    // 3. Construct the message
    $text = "PING from server! If you received this, the core messaging function is working.";
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $payload = [
        'chat_id' => $admin_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    error_log("Payload constructed for admin ID: " . $admin_id);

    // 4. Send the message using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    error_log("Sending cURL request to Telegram...");
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // 5. Log the result
    if ($response === false) {
        error_log("cURL Error: " . $curl_error);
    } else {
        error_log("Telegram API Response (HTTP {$http_code}): " . $response);
    }

    if ($http_code === 200) {
        error_log("--- PING SUCCEEDED ---");
    } else {
        error_log("--- PING FAILED ---");
    }

} catch (Throwable $e) {
    error_log("FATAL ERROR in ping.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}

// Always return 200 OK to Telegram to prevent retries.
http_response_code(200);
echo json_encode(['status' => 'ping_attempted']);
?>