<?php
// backend/api/tg_webhook.php

/**
 * Telegram Bot Webhook
 *
 * This script acts as the single entry point for all interactions with the Telegram bot.
 * It handles incoming messages, commands, and callbacks.
 */

// --- BOOTSTRAP ---
// Load configuration, database connection, and error handling.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/error_logger.php';

// Register a global error handler to catch and log all errors.
register_error_handlers();

log_error("Webhook script started.");

// Establish a database connection.
$pdo = getDbConnection();

// --- HELPER FUNCTIONS ---

/**
 * Fetches the content of a file from Telegram.
 * @param string $fileId The file ID provided by Telegram.
 * @return string|false The file content or false on failure.
 */
function getTelegramFileContent(string $fileId) {
    $botToken = TELEGRAM_BOT_TOKEN;
    // Step 1: Get the file path from the file_id
    $filePathUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
    $fileInfoJson = file_get_contents($filePathUrl);
    if ($fileInfoJson === false) {
        log_error("Failed to get file info for file ID {$fileId}.");
        return false;
    }

    $fileInfo = json_decode($fileInfoJson, true);
    if (!$fileInfo['ok']) {
        log_error("Telegram API error getting file info: " . $fileInfo['description']);
        return false;
    }
    $filePath = $fileInfo['result']['file_path'];

    // Step 2: Download the file content
    $fileContentUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
    $fileContent = file_get_contents($fileContentUrl);
    if ($fileContent === false) {
        log_error("Failed to download file content from {$fileContentUrl}.");
        return false;
    }

    return $fileContent;
}

/**
 * Finds a user by their Telegram ID or creates a new one if not found.
 * NOTE: This is a placeholder. In a real app, you'd have a more robust user system.
 * For now, it links the Telegram user ID to a default email.
 * @param int $telegramId The user's Telegram ID.
 * @param PDO $pdo The database connection.
 * @return int The internal user ID.
 */
function getOrCreateUserByTelegramId(int $telegramId, PDO $pdo): int {
    // For this implementation, we'll just use a default user.
    // A more complete implementation would have a 'telegram_id' column in the users table.
    $defaultEmail = 'default.user@example.com';

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$defaultEmail]);
    $user = $stmt->fetch();

    if ($user) {
        return $user['id'];
    } else {
        // Create the default user if they don't exist
        $passwordHash = password_hash('password', PASSWORD_DEFAULT); // Dummy password
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
        $stmt->execute([$defaultEmail, $passwordHash]);
        return $pdo->lastInsertId();
    }
}


/**
 * Handles an incoming document (file upload).
 * @param int $chatId The chat ID.
 * @param int $telegramUserId The Telegram user ID.
 * @param array $document The Telegram document object.
 * @param PDO $pdo The database connection.
 */
function handleResultCommand(int $chatId, string $text, PDO $pdo) {
    $parts = explode(' ', $text, 4);
    if (count($parts) < 4) {
        sendMessage($chatId, "Usage: `/result <rule_name> <YYYY-MM-DD> <numbers>`\nExample: `/result 4D 2024-08-21 1234,5678`");
        return;
    }
    list(, $ruleName, $date, $numbers) = $parts;

    // TODO: A better way to manage rules would be to have a separate interface for it.
    // For now, let's add a default rule if it doesn't exist.
    $stmt = $pdo->prepare("SELECT id FROM lottery_rules WHERE name = ?");
    $stmt->execute([$ruleName]);
    $rule = $stmt->fetch();
    if (!$rule) {
        $stmt = $pdo->prepare("INSERT INTO lottery_rules (name, numbers_drawn, total_numbers, draw_days) VALUES (?, 4, 10000, 'Wed,Sat,Sun')");
        $stmt->execute([$ruleName]);
        $ruleId = $pdo->lastInsertId();
    } else {
        $ruleId = $rule['id'];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO lottery_draws (rule_id, draw_date, winning_numbers) VALUES (?, ?, ?)");
        $stmt->execute([$ruleId, $date, $numbers]);
        $drawId = $pdo->lastInsertId();
        sendMessage($chatId, "Successfully recorded result for `{$ruleName}` on `{$date}`. Draw ID is `{$drawId}`.");
    } catch (Exception $e) {
        log_error("Failed to record result: " . $e->getMessage());
        sendMessage($chatId, "An error occurred while recording the result.");
    }
}

function calculateWinnings(array $betData, array $winningNumbers): float {
    // This is a placeholder for the actual winning logic.
    // A real implementation would be much more complex.
    if (in_array($betData['number'], $winningNumbers)) {
        return $betData['amount'] * 10; // e.g., win 10x the bet amount
    }
    return 0.0;
}

function handleSettleCommand(int $chatId, string $text, PDO $pdo) {
    $parts = explode(' ', $text);
    if (count($parts) < 2 || !is_numeric($parts[1])) {
        sendMessage($chatId, "Usage: `/settle <draw_id>`");
        return;
    }
    $drawId = (int)$parts[1];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT winning_numbers FROM lottery_draws WHERE id = ?");
        $stmt->execute([$drawId]);
        $draw = $stmt->fetch();

        if (!$draw) {
            sendMessage($chatId, "Draw ID `{$drawId}` not found.");
            $pdo->rollBack();
            return;
        }
        $winningNumbers = explode(',', $draw['winning_numbers']);

        $stmt = $pdo->prepare("SELECT id, bet_data FROM bets WHERE is_settled = 0");
        $stmt->execute();
        $unsettledBets = $stmt->fetchAll();

        $totalWinnings = 0;
        $settledCount = 0;

        $updateStmt = $pdo->prepare("UPDATE bets SET is_settled = 1, winnings = ?, draw_id = ? WHERE id = ?");

        foreach ($unsettledBets as $bet) {
            $betData = json_decode($bet['bet_data'], true);
            $winnings = calculateWinnings($betData, $winningNumbers);

            $updateStmt->execute([$winnings, $drawId, $bet['id']]);

            if ($winnings > 0) {
                $totalWinnings += $winnings;
            }
            $settledCount++;
        }

        $pdo->commit();
        sendMessage($chatId, "Settlement complete for Draw ID `{$drawId}`.\nSettled *{$settledCount}* bets.\nTotal winnings: `{$totalWinnings}`.");

    } catch (Exception $e) {
        $pdo->rollBack();
        log_error("Failed to settle bets for draw {$drawId}: " . $e->getMessage());
        sendMessage($chatId, "An error occurred during settlement.");
    }
}


function handleDocumentUpload(int $chatId, int $telegramUserId, array $document, PDO $pdo) {
    $fileId = $document['file_id'];
    $fileName = $document['file_name'];

    sendMessage($chatId, "Processing your file `{$fileName}`...");

    $fileContent = getTelegramFileContent($fileId);
    if ($fileContent === false) {
        sendMessage($chatId, "Sorry, I could not download the file. Please try again.");
        return;
    }

    // Assumptions:
    // 1. The file is plain text.
    // 2. Each line represents a bet in the format "NUMBER AMOUNT" (e.g., "1234 1").
    $lines = explode("\n", trim($fileContent));
    $parsedBets = [];
    $betCount = 0;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        $parts = preg_split('/\s+/', $line);
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $parsedBets[] = ['number' => $parts[0], 'amount' => (float)$parts[1]];
            $betCount++;
        }
    }

    if ($betCount === 0) {
        sendMessage($chatId, "I parsed your file `{$fileName}` but found no valid bets.");
        return;
    }

    try {
        $pdo->beginTransaction();

        // Get or create the internal user ID
        $userId = getOrCreateUserByTelegramId($telegramUserId, $pdo);

        // Log the raw file upload to chat_logs
        $stmt = $pdo->prepare("INSERT INTO chat_logs (user_id, filename, parsed_data) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $fileName, json_encode($parsedBets)]);
        $chatLogId = $pdo->lastInsertId();

        // Insert each bet into the bets table
        $stmt = $pdo->prepare("INSERT INTO bets (user_id, chat_log_id, bet_data) VALUES (?, ?, ?)");
        foreach ($parsedBets as $bet) {
            $stmt->execute([$userId, $chatLogId, json_encode($bet)]);
        }

        $pdo->commit();

        sendMessage($chatId, "Successfully processed and saved *{$betCount}* bets from `{$fileName}`.");

    } catch (Exception $e) {
        $pdo->rollBack();
        log_error("Failed to process bets from file {$fileName}: " . $e->getMessage());
        sendMessage($chatId, "A database error occurred while processing your file. The administrator has been notified.");
    }
}


/**
 * Sends a message back to the user via the Telegram Bot API.
 * @param int $chatId The chat ID to send the message to.
 * @param string $message The message text.
 */
function sendMessage(int $chatId, string $text) {
    $botToken = TELEGRAM_BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        log_error("Failed to send message to chat ID {$chatId}.");
    }
}


// --- WEBHOOK LOGIC ---

// Get the incoming update from Telegram
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    // If the update is empty, do nothing.
    exit();
}

// Log the entire update for debugging
log_error("Received update: " . json_encode($update, JSON_PRETTY_PRINT));

// Extract the message object
$message = $update['message'] ?? null;
if (!$message) {
    // Not a message update, or format is unexpected.
    exit();
}

$chatId = $message['chat']['id'];
$userId = $message['from']['id'];
$text = trim($message['text'] ?? '');
$document = $message['document'] ?? null;

// Security Check: Only respond to the configured super admin or chat ID
if ($chatId != TELEGRAM_CHAT_ID && $userId != TELEGRAM_SUPER_ADMIN_ID) {
    sendMessage($chatId, "Sorry, I am a private bot.");
    log_error("Unauthorized access attempt from chat ID: {$chatId} and user ID: {$userId}");
    exit();
}


// --- COMMAND ROUTER ---

if (!empty($text)) {
    // Handle text commands
    if (strpos($text, '/') === 0) {
        $command = explode(' ', $text)[0];
        switch ($command) {
            case '/start':
                sendMessage($chatId, "Hello! I am the lottery bot. I am ready to accept bets.");
                break;
            case '/status':
                sendMessage($chatId, "I am running. Database connection is active.");
                break;
            case '/result':
                if ($userId == TELEGRAM_SUPER_ADMIN_ID) {
                    handleResultCommand($chatId, $text, $pdo);
                } else {
                    sendMessage($chatId, "You are not authorized to use this command.");
                }
                break;
            case '/settle':
                if ($userId == TELEGRAM_SUPER_ADMIN_ID) {
                    handleSettleCommand($chatId, $text, $pdo);
                } else {
                    sendMessage($chatId, "You are not authorized to use this command.");
                }
                break;
            default:
                sendMessage($chatId, "Unknown command: `{$command}`");
                break;
        }
    }
} elseif ($document) {
    // Handle file uploads
    handleDocumentUpload($chatId, $userId, $document, $pdo);
} else {
    // Handle other message types if necessary
    sendMessage($chatId, "I can only process text commands and files for now.");
}


// Telegram requires a 200 OK response to know the webhook is working.
http_response_code(200);
echo json_encode(['status' => 'ok']);

?>
