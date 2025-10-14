<?php

// --- Bootstrap ---
// This simulates the environment your webhook runs in.
require_once __DIR__ . '/config.php';

// --- Mocks & Stubs ---
// We don't want to send real Telegram messages during tests.
function sendTelegramMessage($chatId, $text, $replyMarkup = null) {
    echo "--- SENDING MESSAGE ---\n";
    echo "Chat ID: $chatId\n";
    echo "Text: $text\n";
    if ($replyMarkup) {
        echo "Keyboard: " . json_encode($replyMarkup, JSON_PRETTY_PRINT) . "\n";
    }
    echo "-----------------------\n\n";
    return true;
}

function answerCallbackQuery($callbackQueryId, $text = null) {
    echo "--- ANSWERING CALLBACK ---\n";
    echo "Callback ID: $callbackQueryId\n";
    if ($text) {
        echo "Notification Text: $text\n";
    }
    echo "--------------------------\n\n";
    return true;
}

// Mock the state functions to use a simple file
function getUserState($userId) {
    $file = "test_user_states.json";
    if (!file_exists($file)) return null;
    $states = json_decode(file_get_contents($file), true);
    return $states[$userId] ?? null;
}

function setUserState($userId, $state) {
    $file = "test_user_states.json";
    $states = [];
    if (file_exists($file)) {
        $states = json_decode(file_get_contents($file), true);
    }
    if ($state === null) {
        unset($states[$userId]);
    } else {
        $states[$userId] = $state;
    }
    file_put_contents($file, json_encode($states));
}

// --- Test Runner ---
function runTest($testName, $testFunction) {
    echo "===== Running Test: $testName =====\n";
    // Reset state before each test
    if (file_exists('test_user_states.json')) {
        unlink('test_user_states.json');
    }
    try {
        $testFunction();
        echo "✅ PASSED: $testName\n\n";
    } catch (Exception $e) {
        echo "❌ FAILED: $testName\n";
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

// --- Test Cases ---

// Test 1: Initial /start command
runTest("Initial /start command", function() {
    require __DIR__ . '/telegramWebhook.php'; // We need to re-include it to access the handler
    handleRequest(12345, 98765, '/start', false);
});

// Test 2: User Management callback
runTest("User Management callback", function() {
    require __DIR__ . '/telegramWebhook.php';
    handleRequest(12345, 98765, 'user_management', true);
});

// Test 3: List Users callback
runTest("List Users callback", function() {
    // This requires a mock for getAllUsers()
    function getAllUsers() {
        return [
            ['email' => 'test1@example.com', 'created_at' => '2023-01-01'],
            ['email' => 'test2@example.com', 'created_at' => '2023-01-02'],
        ];
    }
    require __DIR__ . '/telegramWebhook.php';
    handleRequest(12345, 98765, 'list_users', true);
});

// Test 4: Entering Delete User state
runTest("Entering Delete User state", function() {
    require __DIR__ . '/telegramWebhook.php';
    handleRequest(12345, 98765, 'delete_user', true);
    // Now check if the state was set correctly
    $state = getUserState(12345);
    if ($state !== 'awaiting_user_deletion') {
        throw new Exception("State was not set to 'awaiting_user_deletion', found: " . ($state ?? 'null'));
    }
});

// Test 5: Providing an email to delete
runTest("Providing an email to delete", function() {
    // Set the state first
    setUserState(12345, 'awaiting_user_deletion');

    // Mock the DB operation
    function deleteUserByEmail($email) {
        echo "--- DB MOCK: Deleting user $email ---\n";
        return $email === 'test@example.com';
    }

    require __DIR__ . '/telegramWebhook.php';
    // Simulate the user sending the email
    handleRequest(12345, 98765, 'test@example.com', false);

    // Check that the state was cleared
    $state = getUserState(12345);
    if ($state !== null) {
        throw new Exception("State was not cleared after deletion attempt.");
    }
});


// Cleanup the test state file
if (file_exists('test_user_states.json')) {
    unlink('test_user_states.json');
}

echo "===== All Tests Complete =====\n";
?>