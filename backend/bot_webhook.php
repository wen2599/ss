<?php
require_once __DIR__ . '/config/database.php';

function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        putenv(sprintf('%s=%s', $name, $value));
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
loadEnv(__DIR__ . '/.env');


$botToken = getenv('BOT_TOKEN');
$adminId = getenv('ADMIN_TELEGRAM_ID');

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit();
}

if (isset($update['message']['from']['id']) && $update['message']['from']['id'] == $adminId) {
    
    $message = $update['message'];
    $text = $message['text'];

    if (preg_match('/^开奖\s+(\d{4}-\d{2}-\d{2})\s+([a-zA-Z0-9]+)$/', $text, $matches)) {
        $issueDate = $matches[1];
        $lotteryNumber = $matches[2];
        
        $conn = getDbConnection();
        
        $stmt = $conn->prepare("INSERT INTO lottery_numbers (issue_date, number) VALUES (?, ?) ON DUPLICATE KEY UPDATE number = VALUES(number)");
        $stmt->bind_param("ss", $issueDate, $lotteryNumber);
        
        if ($stmt->execute()) {
            // 可以选择性地回复管理员，告知操作成功
            // file_get_contents("https://api.telegram.org/bot{$botToken}/sendMessage?chat_id={$adminId}&text=号码 {$lotteryNumber} 已记录");
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>