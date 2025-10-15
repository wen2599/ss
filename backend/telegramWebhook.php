<?php

// --- Failsafe Webhook Script ---
// This script sends a status message at every step to diagnose the issue.

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Enhanced Failsafe .env Loader ---
// This more robust loader populates both putenv and $_ENV for wider compatibility.
function failsafe_load_env() {
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) {
        // This is a critical failure, we should try to report it if possible.
        // The sender function might not have credentials, but we try anyway.
        send_failsafe_message("<b>❌ FATAL FAILURE: .env file not found.</b> Bot cannot be configured.");
        exit();
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Remove surrounding quotes
            if (strlen($value) > 1 && (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
                $value = substr($value, 1, -1);
            }
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// --- Enhanced Failsafe Telegram Sender ---
// This version can be called even before the env is loaded to report critical errors.
function send_failsafe_message($text) {
    // Attempt to get credentials, but handle the case where they might not be loaded yet.
    $bot_token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN');
    $admin_id = $_ENV['TELEGRAM_ADMIN_ID'] ?? getenv('TELEGRAM_ADMIN_ID');

    if (!$bot_token || !$admin_id) {
        return; // Silently fail if not configured
    }

    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $payload = [
        'chat_id' => $admin_id,
        'text' => "BOT STATUS: " . $text,
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
failsafe_load_env(); // Load environment variables first.
send_failsafe_message("<b>1. Webhook triggered.</b> Environment loaded.");

// --- Step 2: Load Core Files ---
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/telegram_helpers.php';
    send_failsafe_message("<b>2. Core files loaded.</b> <code>config.php</code> & <code>telegram_helpers.php</code> included.");
} catch (Throwable $e) {
    send_failsafe_message("<b>❌ FATAL ERROR at Step 2.</b> Failed to load core files. Error: " . $e->getMessage());
    exit();
}

// --- Step 3: Security Validation (Enhanced Debugging) ---
$secretTokenFromEnv = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? null;
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

// For debugging, let's report what we've found.
$envTokenDisplay = $secretTokenFromEnv ? 'found and is ' . strlen($secretTokenFromEnv) . ' chars long.' : 'is NOT SET in .env file!';
$receivedTokenDisplay = $receivedToken ? 'received and is ' . strlen($receivedToken) . ' chars long.' : 'was NOT RECEIVED from Telegram.';
send_failsafe_message("<b>3. Validating Security.</b>\n- .env token: <code>" . $envTokenDisplay . "</code>\n- Header token: <code>" . $receivedTokenDisplay . "</code>");

if (empty($secretTokenFromEnv) || $receivedToken !== $secretTokenFromEnv) {
    send_failsafe_message("<b>❌ FAILURE at Step 3.</b> Security token mismatch or not configured. Request forbidden.");
    http_response_code(403);
    exit('Forbidden: Secret token mismatch.');
}
send_failsafe_message("<b>3a. Security token validated successfully.</b>");


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
send_failsafe_message("<b>6. Data extracted.</b> ChatID: {$chatId}, UserID: {$userId}, Command: '{$command}'");

// --- Step 7: Admin Verification ---
$adminUserId = $_ENV['TELEGRAM_ADMIN_ID'] ?? null;
if (empty($adminUserId) || (string)$userId !== (string)$adminUserId) {
    send_failsafe_message("<b>❌ FAILURE at Step 7.</b> Received message from unauthorized User ID: {$userId}. Expected: {$adminUserId}.");
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