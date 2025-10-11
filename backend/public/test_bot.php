<?php

// --- Standalone Bot Test Script ---

// Enable full error reporting for this script
ini_set('display_errors', 1);
error_reporting(E_ALL);

$log_file = __DIR__ . '/test_bot.log';

function test_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = is_string($message) ? $message : print_r($message, true);
    file_put_contents($log_file, "[$timestamp] " . $log_message . "\n", FILE_APPEND);
}

test_log("--- Test script started ---");

try {
    test_log("Loading dependencies...");
    require_once __DIR__ . '/../src/config.php';
    test_log("Loaded config.php");
    require_once __DIR__ . '/../src/core/Database.php';
    test_log("Loaded Database.php");
    require_once __DIR__ . '/../src/core/Telegram.php';
    test_log("Loaded Telegram.php");
    require_once __DIR__ . '/../src/api/telegramWebhook.php';
    test_log("Loaded telegramWebhook.php");

    test_log("Simulating a '/start' message...");
    $test_message = [
        'message_id' => 12345,
        'from' => [
            'id' => 98765,
            'is_bot' => false,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'language_code' => 'en',
        ],
        'chat' => [
            'id' => 98765,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'type' => 'private',
        ],
        'date' => time(),
        'text' => '/start',
    ];

    test_log("Calling handleUserMessage function...");
    handleUserMessage($test_message);
    test_log("handleUserMessage function finished.");

    echo "Test script executed successfully. Check the test_bot.log file for details.";

} catch (Throwable $t) {
    $error_message = "Caught a throwable: " . $t->getMessage() . " in " . $t->getFile() . " on line " . $t->getLine();
    test_log($error_message);
    echo "An error occurred: " . $error_message;
}

test_log("--- Test script finished ---");