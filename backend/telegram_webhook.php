<?php
// backend/telegram_webhook.php
// Handles incoming updates from the Telegram Bot API, with stateful interactions.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/telegram_helpers.php';
require_once __DIR__ . '/user_state_manager.php';
require_once __DIR__ . '/db_operations.php';

// --- Security Check ---
// Verify the request is coming from Telegram using the secret token.
$secretToken = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
$telegramSecretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (empty($secretToken) || $telegramSecretHeader !== $secretToken) {
    http_response_code(403);
    die('Forbidden: Invalid secret token.');
}

// Get the update from the request body.
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    // No update data received.
    exit();
}

// --- Process Update ---
// Check if the update is a message and contains text.
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = trim($message['text']);
    $userId = $message['from']['id']; // Telegram user ID

    // Check if the user exists in our database, if not, create a basic entry
    $dbUser = fetchOne($pdo, "SELECT id, telegram_chat_id, user_state, state_data FROM users WHERE telegram_chat_id = :chat_id", [':chat_id' => $chatId]);
    if (!$dbUser) {
        // Create a temporary user entry if it's a new Telegram chat ID
        insert($pdo, 'users', [
            'username' => 'telegram_user_' . $chatId,
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), // Dummy password
            'email' => 'telegram_user_' . $chatId . '@example.com', // Dummy email
            'telegram_chat_id' => $chatId,
            'user_state' => STATE_NONE
        ]);
        $dbUser = fetchOne($pdo, "SELECT id, telegram_chat_id, user_state, state_data FROM users WHERE telegram_chat_id = :chat_id", [':chat_id' => $chatId]);
    }

    $currentStateAndData = getUserStateAndData($pdo, $chatId);
    $currentState = $currentStateAndData['state'];
    $stateData = $currentStateAndData['data'];

    // --- Command Handling ---
    if ($text[0] === '/') {
        // Clear state if a new command is issued
        clearUserStateAndData($pdo, $chatId);
        $command = explode(' ', $text)[0];
        switch ($command) {
            case '/start':
                $responseText = "Welcome! This bot helps you manage your bills.\n\n"
                              . "You can use the following commands:\n"
                              . "/register - Create a new account\n"
                              . "/login - Connect your Telegram to an existing account\n"
                              . "/bills - View your latest bills";
                sendTelegramMessage($chatId, $responseText);
                break;

            case '/register':
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_REGISTER_USERNAME);
                sendTelegramMessage($chatId, "Let's create an account. Please enter your desired username:");
                break;

            case '/login':
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_LOGIN_USERNAME);
                sendTelegramMessage($chatId, "Let's link your account. Please enter your username or email:");
                break;

            case '/bills':
                $userFromDb = fetchOne($pdo, "SELECT id, username FROM users WHERE telegram_chat_id = :chat_id AND username IS NOT NULL AND email IS NOT NULL AND password IS NOT NULL", [':chat_id' => $chatId]);
                if ($userFromDb && !str_starts_with($userFromDb['username'], 'telegram_user_')) {
                    $bills = fetchAll($pdo, "SELECT subject, amount, due_date FROM bills WHERE user_id = :user_id ORDER BY received_at DESC LIMIT 5", [':user_id' => $userFromDb['id']]);
                    if ($bills) {
                        $responseText = "<b>Your latest bills:</b>\n\n";
                        foreach ($bills as $bill) {
                            $responseText .= "- " . htmlspecialchars($bill['subject']) . " - $" . ($bill['amount'] ?? 'N/A') . " (Due: " . ($bill['due_date'] ?? 'N/A') . ")\n";
                        }
                    } else {
                        $responseText = "You don't have any bills yet.";
                    }
                } else {
                    $responseText = "You are not fully registered or logged in. Please use /register or /login first.";
                }
                sendTelegramMessage($chatId, $responseText);
                break;

            default:
                sendTelegramMessage($chatId, "Unknown command. Please use /start to see the list of available commands.");
                break;
        }
    } else {
        // --- State-based Input Handling ---
        switch ($currentState) {
            case STATE_AWAITING_REGISTER_USERNAME:
                $stateData['username'] = $text;
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_REGISTER_EMAIL, $stateData);
                sendTelegramMessage($chatId, "Got it. Now, please enter your email address:");
                break;
            case STATE_AWAITING_REGISTER_EMAIL:
                if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    sendTelegramMessage($chatId, "That doesn't look like a valid email. Please try again:");
                    break;
                }
                $stateData['email'] = $text;
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_REGISTER_PASSWORD, $stateData);
                sendTelegramMessage($chatId, "Thanks. Finally, please choose a strong password:");
                break;
            case STATE_AWAITING_REGISTER_PASSWORD:
                $stateData['password'] = $text;
                
                // Attempt to register the user
                $newUserId = registerUserViaTelegram($pdo, $stateData['username'], $stateData['email'], $stateData['password'], $chatId);

                if ($newUserId) {
                    sendTelegramMessage($chatId, "Registration successful! Your account is now linked. You can now use /bills.");
                    clearUserStateAndData($pdo, $chatId);
                } else {
                    sendTelegramMessage($chatId, "Registration failed. Username or email might already exist. Please try /register again with different details or /login if you have an account.");
                    clearUserStateAndData($pdo, $chatId);
                }
                break;

            case STATE_AWAITING_LOGIN_USERNAME:
                $stateData['username_or_email'] = $text;
                setUserStateAndData($pdo, $chatId, STATE_AWAITING_LOGIN_PASSWORD, $stateData);
                sendTelegramMessage($chatId, "Please enter your password:");
                break;
            case STATE_AWAITING_LOGIN_PASSWORD:
                $user = authenticateTelegramUser($pdo, $stateData['username_or_email'], $text);
                if ($user) {
                    // Link Telegram chat ID to existing user
                    linkTelegramUser($pdo, $user['id'], $chatId);
                    sendTelegramMessage($chatId, "Login successful! Your Telegram is now linked to your account.");
                    clearUserStateAndData($pdo, $chatId);
                } else {
                    sendTelegramMessage($chatId, "Login failed. Invalid username/email or password. Please try /login again.");
                    clearUserStateAndData($pdo, $chatId);
                }
                break;

            case STATE_NONE:
            default:
                sendTelegramMessage($chatId, "I didn't understand that. Please use a command like /start, /register, or /login.");
                break;
        }
    }
}

// Acknowledge receipt of the update to Telegram.
http_response_code(200);
echo json_encode(['status' => 'ok']);
