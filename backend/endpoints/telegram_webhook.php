<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/lib/telegram_utils.php';

// Function to get the database connection
function get_db_connection() {
    $host = DB_HOST;
    $db   = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}

// Get the raw POST data from Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit();
}

// Make sure it's a channel post
if (isset($update['channel_post'])) {
    $message = $update['channel_post'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'];
    $user_id = $message['from']['id'];

    // Security check: only the admin can post results
    if ($user_id != TELEGRAM_ADMIN_ID) {
        send_telegram_message($chat_id, "Unauthorized user: " . $user_id);
        exit();
    }

    // Parse the message text
    // Expected format: "Lottery Type: 1, 2, 3, 4, 5"
    list($lottery_type, $numbers_str) = explode(':', $text, 2);
    $lottery_type = trim($lottery_type);
    $numbers = trim($numbers_str);

    if ($lottery_type && $numbers) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("INSERT INTO lottery_results (lottery_type, numbers) VALUES (?, ?)");
            $stmt->execute([$lottery_type, $numbers]);
            send_telegram_message($chat_id, "Successfully added results for " . $lottery_type);
        } catch (\PDOException $e) {
            send_telegram_message($chat_id, "Error saving results: " . $e->getMessage());
        }
    } else {
        send_telegram_message($chat_id, "Invalid format. Use 'Lottery Type: 1, 2, 3...'");
    }
}
?>