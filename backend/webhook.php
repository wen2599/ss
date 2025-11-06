<?php
// webhook.php

// --- Enhanced Debug Logging ---
// Log file path
$log_file = __DIR__ . '/webhook_debug.log';

// Get raw request body
$input = file_get_contents('php://input');

// Get all headers
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (substr($key, 0, 5) === 'HTTP_') {
        $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
        $headers[$header] = $value;
    }
}

// Prepare log entry
$log_entry = "--- [" . date('Y-m-d H:i:s') . "] ---\n";
$log_entry .= "Request Body:\n" . ($input ? $input : "Empty") . "\n\n";
$log_entry .= "Headers:\n" . json_encode($headers, JSON_PRETTY_PRINT) . "\n\n";
$log_entry .= "------------------------------------------\n\n";

// Append to log file
file_put_contents($log_file, $log_entry, FILE_APPEND);


// --- Main Webhook Logic ---
try {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);

    // Config and Database are now loaded inside the try block for graceful error handling.
    require_once 'database.php';

    // --- Security Check ---
    $secret_token_header = isset($headers['X-Telegram-Bot-Api-Secret-Token']) ? $headers['X-Telegram-Bot-Api-Secret-Token'] : '';
    $expected_secret = get_env_variable('TELEGRAM_WEBHOOK_SECRET');

    if (empty($expected_secret) || $secret_token_header !== $expected_secret) {
        if (empty($expected_secret)) {
            error_log("Webhook Forbidden: TELEGRAM_WEBHOOK_SECRET is empty in .env or failed to load.");
        } else {
            error_log("Webhook Forbidden: Secret token mismatch. Header: [".$secret_token_header."], Expected: [".$expected_secret."]");
        }
        http_response_code(403);
        // Exit cleanly after setting the status code.
        exit();
    }

    // --- Input Handling ---
    // We already read the input for logging, so we reuse it here.
    $update = json_decode($input, true);

    if (!$update) {
        http_response_code(400);
        exit(); // Exit cleanly.
    }

    // --- Parsing Logic ---
    function parseLotteryText($text) {
    $lottery_type = null;
    $issue_number = null;
    $numbers = null;

    // Example pattern: "香港六合彩 24071期"
    if (preg_match('/(香港六合彩)\s+(\d+期)/', $text, $matches)) {
        $lottery_type = $matches[1];
        $issue_number = $matches[2];
    }

    // Example pattern: "开奖结果: 01 02 03 04 05 06"
    if (preg_match('/开奖结果:\s*([\d\s]+)/', $text, $matches)) {
        $numbers_str = trim($matches[1]);
        // Normalize spaces
        $numbers = preg_replace('/\s+/', ' ', $numbers_str);
    }

    if ($lottery_type && $issue_number && $numbers) {
        return [
            'lottery_type' => $lottery_type,
            'issue_number' => $issue_number,
            'numbers'      => $numbers,
        ];
    }
    return null;
}


// --- Main Logic ---
if (isset($update['channel_post']['text'])) {
    $message_text = $update['channel_post']['text'];
    $parsed_data = parseLotteryText($message_text);
    
    if ($parsed_data) {
        if (!Database::saveLotteryResult($parsed_data['lottery_type'], $parsed_data['issue_number'], $parsed_data['numbers'])) {
            error_log("Database Error: Failed to save parsed lottery result for issue " . $parsed_data['issue_number']);
        }
    } else {
        error_log("Parsing Error: Could not parse lottery data from message.");
    }
}

// --- Success Response ---
// If we reach here, everything was successful.
http_response_code(200);
echo "OK";

} catch (Exception $e) {
    // --- Global Exception Handler ---
    // Log any exceptions that were not caught earlier.
    error_log("Webhook Unhandled Exception: " . $e->getMessage());
    // Respond with a generic 500 error to avoid leaking implementation details.
    http_response_code(500);
    echo "Internal Server Error";
}
?>