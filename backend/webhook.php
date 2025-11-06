<?php
// webhook.php

try {
    // The config file contains the DotEnv loading and helpers like get_env_variable()
    require_once 'config.php';
    require_once 'database.php';
    // The utils file contains the get_all_headers() polyfill
    require_once 'utils.php';

    // --- Security Check ---
    $headers = get_all_headers();
    $secret_token_header = isset($headers['X-Telegram-Bot-Api-Secret-Token']) ? $headers['X-Telegram-Bot-Api-Secret-Token'] : '';

    $expected_secret = get_env_variable('TELEGRAM_WEBHOOK_SECRET');

    // It's crucial to check if the expected secret is configured.
    if (empty($expected_secret) || $secret_token_header !== $expected_secret) {
        if (empty($expected_secret)) {
            error_log("Webhook Forbidden: TELEGRAM_WEBHOOK_SECRET is not configured on the server.");
        } else {
            error_log("Webhook Forbidden: Secret token mismatch. Header: [".$secret_token_header."], Expected: [".$expected_secret."]");
        }
        http_response_code(403);
        exit('Forbidden');
    }

    // --- Input Handling ---
    $input = file_get_contents('php://input');
    if (empty($input)) {
        http_response_code(400);
        exit('Bad Request: Empty payload.');
    }

    $update = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Webhook JSON Decode Error: " . json_last_error_msg());
        http_response_code(400);
        exit('Bad Request: Invalid JSON.');
    }

    // --- Parsing Logic ---
    function parseLotteryText($text) {
        $lottery_type = null;
        $issue_number = null;
        $numbers = null;

        if (preg_match('/^\s*([^\s]+?)\s+(\d+期)/u', $text, $matches)) {
            $lottery_type = trim($matches[1]);
            $issue_number = trim($matches[2]);
        }

        if (preg_match('/开奖结果:?\s*([\d\s]+)/u', $text, $matches)) {
            $numbers_str = trim($matches[1]);
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
                error_log("Webhook DB Error: Failed to save parsed lottery result for issue " . $parsed_data['issue_number']);
            }
        } else {
            error_log("Webhook Parsing Error: Could not parse lottery data from message text: \"{$message_text}\"");
        }
    }

    // --- Success Response ---
    http_response_code(200);
    echo "OK";

} catch (Exception $e) {
    // --- Global Exception Handler ---
    error_log("Webhook Unhandled Exception: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
    http_response_code(500);
    echo "Internal Server Error";
}
?>