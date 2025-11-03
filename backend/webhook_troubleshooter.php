<?php
// backend/webhook_troubleshooter.php

// --- Step 0: Aggressive Error Reporting ---
// This is critical for catching silent errors.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Immediately respond to Telegram to prevent timeouts.
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Troubleshooter is running.']);

// --- Step 1: Initialize Debug Report ---
$debug_report = "*Webhook Troubleshooter Report*\n\n";
$debug_report .= "1. Script started at " . date('Y-m-d H:i:s T') . "\n";

// Function to send the final report to the admin
function send_debug_report($report, $bot_token, $admin_id) {
    if (!$bot_token || !$admin_id) return;
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $post_fields = [
        'chat_id' => $admin_id,
        'text' => $report,
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// Set a shutdown function to guarantee a report is sent, even on fatal errors.
register_shutdown_function(function() use (&$debug_report, &$bot_token, &$admin_id, &$report_sent) {
    if (!$report_sent) {
        $last_error = error_get_last();
        if ($last_error) {
            $debug_report .= "\n*FATAL ERROR:* " . $last_error['message'] . " in " . $last_error['file'] . " on line " . $last_error['line'];
        } else {
            $debug_report .= "\n*Execution ended unexpectedly.*";
        }
        send_debug_report($debug_report, $bot_token, $admin_id);
    }
});

$bot_token = null;
$admin_id = null;
$report_sent = false;

// --- Main Execution Block ---
try {
    // --- Step 2: Load Configuration ---
    $debug_report .= "2. Attempting to load config...\n";
    require_once __DIR__ . '/utils/config_loader.php';
    $debug_report .= "   - `config_loader.php` included successfully.\n";

    $bot_token = getenv('TELEGRAM_BOT_TOKEN');
    $admin_id = getenv('TELEGRAM_ADMIN_ID');

    $debug_report .= $bot_token ? "   - Bot Token: Loaded.\n" : "   - *Bot Token: NOT FOUND.*\n";
    $debug_report .= $admin_id ? "   - Admin ID: Loaded.\n" : "   - *Admin ID: NOT FOUND.*\n";

    if (!$bot_token || !$admin_id) {
        throw new Exception("Missing critical environment variables.");
    }

    // --- Step 3: Read Incoming Data ---
    $debug_report .= "3. Reading `php://input`...\n";
    $raw_input = file_get_contents('php://input');
    $debug_report .= "   - `php://input` read. Length: " . strlen($raw_input) . " bytes.\n";
    if (strlen($raw_input) > 1000) {
        $raw_input_display = substr($raw_input, 0, 1000) . "... (truncated)";
    } else {
        $raw_input_display = $raw_input ?: '(empty)';
    }

    // --- Step 4: Decode JSON ---
    $debug_report .= "4. Decoding JSON...\n";
    $data = json_decode($raw_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Decode Error: " . json_last_error_msg());
    }
    $debug_report .= "   - JSON decoded successfully.\n";

    // --- Step 5: Check Data Structure ---
    $debug_report .= "5. Analyzing data structure...\n";
    if (isset($data['message'])) {
        $debug_report .= "   - Type: Private Message\n";
        $debug_report .= "   - Text: " . ($data['message']['text'] ?? 'N/A') . "\n";
    } elseif (isset($data['channel_post'])) {
        $debug_report .= "   - Type: Channel Post\n";
        $debug_report .= "   - Text: " . ($data['channel_post']['text'] ?? 'N/A') . "\n";
    } else {
        $debug_report .= "   - *Type: Unknown or not a message.*\n";
    }

    $debug_report .= "\n*Execution finished successfully.*";

} catch (Throwable $e) {
    // Catch any exception and add it to the report
    $debug_report .= "\n*EXCEPTION CAUGHT:* " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
}

// --- Step 6: Send the Final Report ---
send_debug_report($debug_report, $bot_token, $admin_id);
$report_sent = true;

?>
