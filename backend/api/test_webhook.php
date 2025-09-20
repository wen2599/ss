<?php
// backend/api/test_webhook.php

/**
 * Test script for tg_webhook.php
 *
 * This script simulates a Telegram update and sends it to the webhook via cURL.
 * This allows for testing the webhook logic without needing to expose it to the public internet.
 */

// The URL of the webhook script
$webhookUrl = 'http://localhost/backend/api/tg_webhook.php'; // Assuming a local server setup

// --- Test Payloads ---

$startCommandPayload = [
    'update_id' => 123456789,
    'message' => [
        'message_id' => 1,
        'from' => [
            'id' => 1878794912, // Super Admin ID
            'is_bot' => false,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
        ],
        'chat' => [
            'id' => 12345, // A dummy chat ID
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'type' => 'private',
        ],
        'date' => time(),
        'text' => '/start',
    ],
];


// --- cURL Execution ---

function sendTestRequest(string $url, array $payload) {
    echo "--- Testing Payload ---\n";
    echo json_encode($payload, JSON_PRETTY_PRINT) . "\n";
    echo "-----------------------\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Response (HTTP {$httpCode}):\n";
    echo $response . "\n\n";
}


$statusCommandPayload = $startCommandPayload;
$statusCommandPayload['message']['text'] = '/status';

$resultCommandPayload = $startCommandPayload;
$resultCommandPayload['message']['text'] = '/result 4D 2025-09-20 1234,5678';

// We need a draw ID for the settle command, which we get from the result command.
// In a real test suite, we would run the result command, get the ID from the database,
// and then use it here. For this simple script, we'll assume the draw ID is 1.
$settleCommandPayload = $startCommandPayload;
$settleCommandPayload['message']['text'] = '/settle 1';


// --- RUN TESTS ---

// Test the /start command
sendTestRequest($webhookUrl, $startCommandPayload);

// Test the /status command
sendTestRequest($webhookUrl, $statusCommandPayload);

// Test the /result command
sendTestRequest($webhookUrl, $resultCommandPayload);

// Test the /settle command
sendTestRequest($webhookUrl, $settleCommandPayload);

?>
