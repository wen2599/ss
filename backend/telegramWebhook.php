<?php

// Failsafe Webhook Script
// This script sends a status message at every step to diagnose the issue.

// --- Failsafe Error Reporting ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

<?php

// FINAL DIAGNOSTIC SCRIPT
// This version hardcodes credentials to bypass any .env file reading issues.
// This is for TESTING ONLY and is NOT secure for production.

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Hardcoded Credentials for Final Test ---
define('TELEGRAM_BOT_TOKEN', '7222421940:AAEUTuFvonFCP1o-nRtNWbojCzSM9GQ--jU');
define('TELEGRAM_ADMIN_ID', '1878794912');
define('TELEGRAM_WEBHOOK_SECRET', 'A7kZp9sR3bV2nC1mE6gH_jL5tP8vF4qW');


// --- Self-Contained Telegram Sender ---
function send_final_diagnostic($text) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $payload = [
        'chat_id' => TELEGRAM_ADMIN_ID,
        'text' => "FINAL DIAGNOSTIC: " . $text,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

// --- Start Execution ---
send_failsafe_message("<b>1. Webhook triggered.</b> Script starting execution.");

// --- Step 2: Load Core Files ---
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/telegram_helpers.php';
    send_failsafe_message("<b>2. Core files loaded.</b> <code>config.php</code> and <code>telegram_helpers.php</code> were included successfully.");
} catch (Throwable $e) {
    send_failsafe_message("<b>❌ FATAL ERROR at Step 2.</b> Failed to load core files. Error: " . $e->getMessage());
    exit();
}

// --- Step 3: Security Validation ---
$secretToken = getenv('TELEGRAM_WEBHOOK_SECRET');
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (empty($secretToken) || $receivedToken !== $secretToken) {
    send_failsafe_message("<b>❌ FAILURE at Step 3.</b> Security token mismatch or not configured. Request will be forbidden.");
    http_response_code(403);
    exit('Forbidden: Secret token mismatch.');
}
send_failsafe_message("<b>3. Security token validated.</b>");


// --- Step 4: Read Input ---
$raw_input = file_get_contents('php://input');
if ($raw_input === false || empty($raw_input)) {
    send_failsafe_message("<b>❌ FAILURE at Step 4.</b> Raw input from Telegram was empty or could not be read.");
    exit();
}
send_failsafe_message("<b>4. Raw input received.</b> Length: " . strlen($raw_input) . " bytes.");


// --- Step 5: Decode JSON ---
$update = json_decode($raw_input, true);
if ($update === null) {
    send_failsafe_message("<b>❌ FAILURE at Step 5.</b> Failed to decode JSON from raw input.");
    exit();
}
send_failsafe_message("<b>5. JSON decoded successfully.</b>");


// --- Step 6: Extract Data ---
if (!isset($update['message'])) {
    send_failsafe_message("<b>EXIT at Step 6.</b> Update was not a 'message' type. Nothing to do.");
    exit();
}
$message = $update['message'];
$chatId = $message['chat']['id'];
$userId = $message['from']['id'] ?? $chatId;
$command = trim($message['text'] ?? '');
send_failsafe_message("<b>6. Data extracted.</b> ChatID: {$chatId}, Command: '{$command}'");

// --- Step 7: Admin Verification ---
$adminUserId = getenv('TELEGRAM_ADMIN_ID');
if (empty($adminUserId) || (string)$userId !== (string)$adminUserId) {
    send_failsafe_message("<b>❌ FAILURE at Step 7.</b> Received message from an unauthorized User ID: {$userId}. Expected: {$adminUserId}.");
    // Do not exit, but you could send a gentle message to the user if you wanted.
    // For security, we just stop processing.
    http_response_code(200); // Respond OK to Telegram to prevent webhook retries
    exit();
}
send_failsafe_message("<b>7. Admin verified.</b>");

// --- Step 8: Process Command ---
try {
    send_failsafe_message("<b>8. Processing command...</b> Calling <code>processCommand()</code>.");
    processCommand($chatId, $userId, $command);
    send_failsafe_message("<b>✅ SUCCESS!</b> Command processing finished without errors.");
} catch (Throwable $e) {
    send_failsafe_message("<b>❌ FATAL ERROR at Step 8.</b> An error occurred inside <code>processCommand()</code>: " . $e->getMessage());
    exit();
}

// Acknowledge receipt to Telegram's server.
http_response_code(200);
echo json_encode(['status' => 'ok']);

?>