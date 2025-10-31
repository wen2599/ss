<?php
// 此脚本应通过 Cron Job 定时执行

require_once __DIR__ . '/env_loader.php';

$bot_token = getenv('TELEGRAM_BOT_TOKEN');
$offset_file = __DIR__ . '/telegram_offset.txt';

// 读取上次的 offset
$offset = file_exists($offset_file) ? (int)file_get_contents($offset_file) : 0;

$api_url = "https://api.telegram.org/bot{$bot_token}/getUpdates?offset={$offset}&timeout=60";

// 使用 cURL 获取更新
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!$data || !$data['ok']) {
    // 可以在此记录错误日志
    exit;
}

if (empty($data['result'])) {
    // 没有新消息
    exit;
}

// 连接数据库
$db_conn = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
if ($db_conn->connect_error) {
    // 记录错误
    exit;
}
$db_conn->set_charset("utf8mb4");

$stmt = $db_conn->prepare("INSERT INTO lottery_results (issue_number, numbers, draw_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numbers=VALUES(numbers), draw_date=VALUES(draw_date)");

foreach ($data['result'] as $update) {
    // 处理消息，这里假设消息格式为 "24078期 开奖号码: 01,02,03,04,05,06+07"
    if (isset($update['channel_post']['text'])) {
        $text = $update['channel_post']['text'];
        
        // 使用正则表达式解析
        if (preg_match('/(d{5,})期s*开奖号码[:：s]*([d,]+)+([d]{2})/', $text, $matches)) {
            $issue_number = $matches[1];
            $regular_numbers = $matches[2];
            $special_number = $matches[3];
            $full_numbers = $regular_numbers . '+' . $special_number;
            $draw_date = date('Y-m-d'); // 假设开奖日期为当天

            $stmt->bind_param("sss", $issue_number, $full_numbers, $draw_date);
            $stmt->execute();
        }
    }
    // 更新 offset
    $offset = $update['update_id'] + 1;
}

$stmt->close();
$db_conn->close();

// 保存新的 offset
file_put_contents($offset_file, $offset);

echo "Processed up to update_id: " . ($offset - 1) . "\n";
