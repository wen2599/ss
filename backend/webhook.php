<?php
// webhook.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once 'database.php';

// --- Security Check ---
$secret_token_header = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) ? $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] : '';
$expected_secret = get_env_variable('TELEGRAM_WEBHOOK_SECRET');

if (empty($expected_secret) || $secret_token_header !== $expected_secret) {
    if (empty($expected_secret)) {
        error_log("Webhook Forbidden: TELEGRAM_WEBHOOK_SECRET is empty in .env or failed to load.");
    } else {
        error_log("Webhook Forbidden: Secret token mismatch. Header: [".$secret_token_header."], Expected: [".$expected_secret."]");
    }
    http_response_code(403);
    exit('Forbidden');
}

// --- Input Handling ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
    http_response_code(400);
    exit('Bad Request');
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

http_response_code(200);
echo "OK";
?>