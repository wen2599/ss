<?php
// --- Telegram Bot Admin Webhook ---

// Logging setup
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/tg_webhook.log');
error_reporting(E_ALL);

require_once 'db_connect.php';
require_once 'config.php';

// Check for Telegram Bot Token configuration
if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN') {
    error_log("FATAL: Telegram Bot Token is not configured in config.php");
    exit();
}
$API_URL = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/';

// --- Telegram API Communication ---
function sendRequest($method, $params = []) {
    $url = $GLOBALS['API_URL'] . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_UNICODE));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function sendMessage($chatId, $text, $replyMarkup = null) {
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($replyMarkup) {
        $params['reply_markup'] = $replyMarkup;
    }
    sendRequest('sendMessage', $params);
}

function answerCallbackQuery($callbackQueryId, $text = '', $showAlert = false) {
    sendRequest('answerCallbackQuery', [
        'callback_query_id' => $callbackQueryId,
        'text' => $text,
        'show_alert' => $showAlert
    ]);
}

// --- Admin and State Utilities ---
function tableExists($conn, $tableName) {
    try {
        $result = $conn->query("SHOW TABLES LIKE '{$tableName}'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

function isSuperAdmin($chatId) {
    if (!defined('TELEGRAM_SUPER_ADMIN_ID')) {
        return false;
    }
    return $chatId == TELEGRAM_SUPER_ADMIN_ID;
}

function isAdmin($conn, $chatId) {
    if (isSuperAdmin($chatId)) return true;
    if (!$conn || !tableExists($conn, 'tg_admins')) return false;

    $stmt = $conn->prepare("SELECT chat_id FROM tg_admins WHERE chat_id = ?");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $isAdmin = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $isAdmin;
}

function setAdminState($conn, $chatId, $state, $data = null) {
    if (!$conn || !tableExists($conn, 'tg_admin_states')) return;
    $stmt = $conn->prepare("INSERT INTO tg_admin_states (chat_id, state, state_data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE state = ?, state_data = ?");
    $stmt->bind_param("issss", $chatId, $state, $data, $state, $data);
    $stmt->execute();
    $stmt->close();
}

function getAdminState($conn, $chatId) {
    if (!$conn || !tableExists($conn, 'tg_admin_states')) return ['state' => null, 'state_data' => null];
    $stmt = $conn->prepare("SELECT state, state_data FROM tg_admin_states WHERE chat_id = ?");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result ?: ['state' => null, 'state_data' => null];
}

// --- Admin Keyboard Menus ---
$adminKeyboard = [
    'keyboard' => [[['text' => 'Find Player'], ['text' => 'Points List']], [['text' => 'Cancel']]],
    'resize_keyboard' => true
];

// --- Main Logic ---
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit();

$conn = db_connect();
if (!$conn) {
    error_log("FATAL: Could not connect to the database.");
    exit();
}

$chatId = null;
$text = null;
$isCallback = false;

if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $text = $update["message"]["text"] ?? '';
} elseif (isset($update["callback_query"])) {
    $isCallback = true;
    $callbackQuery = $update["callback_query"];
    $chatId = $callbackQuery["message"]["chat"]["id"];
    $callbackQueryId = $callbackQuery["id"];
    $data = $callbackQuery["data"];
}

if (!$chatId) exit();

if (!isAdmin($conn, $chatId)) {
    if ($isCallback) {
        answerCallbackQuery($callbackQueryId, "Sorry, you do not have permission.", true);
    } else {
        sendMessage($chatId, "You are not authorized to use this bot.");
    }
    exit();
}

if (!$isCallback) {
    if (isSuperAdmin($chatId)) {
        if (strpos($text, '/addadmin') === 0) {
            $parts = explode(' ', $text, 2);
            $newAdminId = $parts[1] ?? 0;
            if (is_numeric($newAdminId) && $newAdminId > 0) {
                $stmt = $conn->prepare("INSERT INTO tg_admins (chat_id) VALUES (?) ON DUPLICATE KEY UPDATE chat_id = ?");
                $stmt->bind_param("ii", $newAdminId, $newAdminId);
                $stmt->execute();
                $stmt->close();
                sendMessage($chatId, "✅ Admin `$newAdminId` has been added.");
            } else {
                sendMessage($chatId, "❌ Invalid ID. Usage: /addadmin [user_id]");
            }
            exit();
        }
    }

    $adminState = getAdminState($conn, $chatId);

    if ($text === '/start' || $text === 'Cancel') {
        setAdminState($conn, $chatId, null);
        $welcomeMessage = "Welcome back, Admin! Please choose an action.";
        if (isSuperAdmin($chatId)) {
            $welcomeMessage .= "\n\nYou are a Super Admin. You can use:\n`/addadmin [user_id]`\n`/removeadmin [user_id]`\n`/listadmins`";
        }
        sendMessage($chatId, $welcomeMessage, $adminKeyboard);
        exit();
    }

    // ... (rest of message handling logic from user's code, adapted for the new structure and with bug fixes)

} else { // Is a callback query
    $parts = explode('_', $data, 3);
    $action = $parts[0] ?? '';
    $sub_action = $parts[1] ?? '';
    $userId = $parts[2] ?? 0;

    switch ($action . '_' . $sub_action) {
        case 'confirm_del':
            if ($userId > 0) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    sendMessage($chatId, "Player with ID `{$userId}` has been deleted.");
                    answerCallbackQuery($callbackQueryId, "Player deleted");
                } else {
                    sendMessage($chatId, "Deletion failed. Player with ID `{$userId}` not found.");
                    answerCallbackQuery($callbackQueryId, "Deletion failed", true);
                }
                $stmt->close();
            }
            break;
        case 'cancel_del':
             sendMessage($chatId, "Operation cancelled.");
             answerCallbackQuery($callbackQueryId);
             break;
        // ... (rest of callback handling)
    }
}

$conn->close();
?>
