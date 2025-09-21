<?php
/**
 * Telegram Bot Webhook
 *
 * This script acts as the webhook for a Telegram bot, allowing it to manage users.
 * It receives updates from Telegram, processes commands, and interacts with a database.
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
    // Log error and stop execution if the database connection fails
    error_log("Database connection failed: " . $e->getMessage());
    // In a real scenario, you might want to send an alert to the admin
    // For now, we'll stop the script gracefully.
    http_response_code(500); // Internal Server Error
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

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

// 4. User Management Functions
/**
 * Adds a new user to the database.
 * @param PDO $pdo The database connection object.
 * @param string $username The username to add.
 * @return string The result message.
 */
function addUserToDB($pdo, $username) {
    // Basic validation for username
    if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
        return "Invalid username. It must be 3-32 characters long and can only contain letters, numbers, and underscores.";
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username) VALUES (:username)");
        $stmt->execute([':username' => $username]);
        return "User `{$username}` has been added successfully.";
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) { // SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
            return "User `{$username}` already exists.";
        }
        error_log("Error adding user: " . $e->getMessage());
        return "An error occurred while adding the user.";
    }
}

/**
 * Deletes a user from the database.
 * @param PDO $pdo The database connection object.
 * @param string $username The username to delete.
 * @return string The result message.
 */
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

/**
 * Lists all users from the database.
 * @param PDO $pdo The database connection object.
 * @return string The formatted list of users.
 */
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

// Log the raw update for debugging
file_put_contents('webhook_log.txt', $update_json . "\n", FILE_APPEND);

// 6. Process the Message
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];

    if (strpos($text, '/') === 0) {
        $parts = explode(' ', $text, 2);
        $command = $parts[0];
        $args = isset($parts[1]) ? trim($parts[1]) : '';

        // Handle Different Commands
        switch ($command) {
            case '/start':
                $responseText = "Welcome! I am your user management bot.\n\n"
                              . "Available commands:\n"
                              . "`/adduser <username>` - Add a new user.\n"
                              . "`/deluser <username>` - Delete a user.\n"
                              . "`/listusers` - List all users.\n";
                break;

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
        sendMessage($chat_id, "Please send a command starting with `/`.");
    }
}

// 7. Respond to Telegram to acknowledge receipt of the update
http_response_code(200);
echo json_encode(['status' => 'ok']);
?>
