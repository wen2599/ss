<?php
/**
 * Telegram Bot Webhook
 *
 * This script acts as the webhook for a Telegram bot, allowing it to manage users.
 * It receives updates from Telegram, processes commands, and interacts with a database.
 * Access to management commands is restricted to the admin user specified in config.php.
 */

// 1. Include Configuration
require_once 'config.php';

// SQL to create the necessary table:
/*
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
*/

// 2. Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed.']));
}

// 3. Define a function to send messages back to Telegram
function sendMessage($chat_id, $text) {
    global $bot_token;
    $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query($data)]];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

// 4. User Management Functions (add, delete, list) - unchanged
function addUserToDB($pdo, $username) {
    if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
        return "Invalid username. It must be 3-32 characters long and can only contain letters, numbers, and underscores.";
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username) VALUES (:username)");
        $stmt->execute([':username' => $username]);
        return "User `{$username}` has been added successfully.";
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            return "User `{$username}` already exists.";
        }
        error_log("Error adding user: " . $e->getMessage());
        return "An error occurred while adding the user.";
    }
}
function deleteUserFromDB($pdo, $username) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->rowCount() > 0) {
            return "User `{$username}` has been deleted.";
        } else {
            return "User `{$username}` not found.";
        }
    } catch (PDOException $e) {
        error_log("Error deleting user: " . $e->getMessage());
        return "An error occurred while deleting the user.";
    }
}
function listUsersFromDB($pdo) {
    try {
        $stmt = $pdo->query("SELECT username FROM users ORDER BY created_at ASC");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($users)) {
            return "There are no users in the database.";
        }
        $userList = "Listing all users:\n";
        foreach ($users as $index => $user) {
            $userList .= ($index + 1) . ". `" . htmlspecialchars($user) . "`\n";
        }
        return $userList;
    } catch (PDOException $e) {
        error_log("Error listing users: " . $e->getMessage());
        return "An error occurred while fetching the user list.";
    }
}

// 5. Get and Decode the Incoming Update
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);
file_put_contents('webhook_log.txt', $update_json . "\n", FILE_APPEND);

// 6. Process the Message
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];

    // Convert admin_id from config to integer for safe comparison
    $admin_id = intval($admin_id);

    if (strpos($text, '/') === 0) {
        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $args = isset($parts[1]) ? trim($parts[1]) : '';

        // Commands accessible to everyone
        if ($command === '/start') {
            $responseText = "Welcome! I am your user management bot.\n\n";
            if ($chat_id === $admin_id) {
                $responseText .= "You are the admin. Available commands:\n"
                               . "`/adduser <username>`\n"
                               . "`/deluser <username>`\n"
                               . "`/listusers`\n";
            } else {
                $responseText .= "You are not authorized to perform any actions.";
            }
            sendMessage($chat_id, $responseText);
            http_response_code(200);
            exit();
        }
        
        // Admin-only commands
        if ($chat_id !== $admin_id) {
            sendMessage($chat_id, "You are not authorized to use this command.");
            http_response_code(403); // Forbidden
            exit();
        }

        switch ($command) {
            case '/adduser':
                if (!empty($args)) {
                    $responseText = addUserToDB($pdo, $args);
                } else {
                    $responseText = "Please provide a username. Usage: `/adduser <username>`";
                }
                break;

            case '/deluser':
                if (!empty($args)) {
                    $responseText = deleteUserFromDB($pdo, $args);
                } else {
                    $responseText = "Please provide a username. Usage: `/deluser <username>`";
                }
                break;

            case '/listusers':
                $responseText = listUsersFromDB($pdo);
                break;

            default:
                $responseText = "Sorry, I don't understand that command.";
                break;
        }
        sendMessage($chat_id, $responseText);

    } else {
        // Handle non-command messages
        if ($chat_id === $admin_id) {
            sendMessage($chat_id, "Please send a command starting with `/`.");
        } else {
            sendMessage($chat_id, "Sorry, I can only respond to authorized users.");
        }
    }
}

// 7. Respond to Telegram to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>
