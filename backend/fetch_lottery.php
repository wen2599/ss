<?php
// backend/fetch_lottery.php

// 这是一个独立的Cron Job脚本

require_once __DIR__ . '/config.php';

// --- 配置 ---
$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'];
$api_url = "https://api.telegram.org/bot{$bot_token}/getUpdates";
$offset_file = __DIR__ . '/last_update_id.txt';

// --- 获取上次处理的 update_id ---
$offset = 0;
if (file_exists($offset_file)) {
    $offset = (int)file_get_contents($offset_file);
}

// --- 请求Telegram API ---
$ch = curl_init();
// 我们请求 offset + 1 的更新，避免重复处理
curl_setopt($ch, CURLOPT_URL, $api_url . '?offset=' . ($offset + 1) . '&limit=100');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response_json = curl_exec($ch);
curl_close($ch);

$response = json_decode($response_json, true);

if (!$response || $response['ok'] === false) {
    // 记录错误或发送通知
    error_log("Telegram API Error: " . ($response['description'] ?? 'Unknown error'));
    exit;
}

// --- 处理消息 ---
$last_update_id = $offset;
foreach ($response['result'] as $update) {
    // 确保我们只处理频道消息
    if (isset($update['channel_post']['text'])) {
        $message_text = $update['channel_post']['text'];
        
        // 使用正则表达式解析消息
        // 示例格式: "2023008期：1,2,3,4,5,6 特 7"
        if (preg_match('/(\d{7})期：([',\s,]+\d) 特 (\d+)/u', $message_text, $matches)) {
            $issue_number = $matches[1];
            $regular_numbers = $matches[2];
            $special_number = $matches[3];
            
            $all_numbers = $regular_numbers . ',' . $special_number;
            
            // 假设开奖日期就是消息发送的日期
            $draw_date = date('Y-m-d', $update['channel_post']['date']);

            try {
                // 插入数据库，因为 issue_number 是 UNIQUE，重复插入会失败
                $stmt = $pdo->prepare(
                    "INSERT INTO lottery_results (issue_number, numbers, draw_date) VALUES (?, ?, ?)"
                );
                $stmt->execute([$issue_number, $all_numbers, $draw_date]);
                echo "Successfully inserted issue: {$issue_number}\n";
            } catch (PDOException $e) {
                // 如果错误码是 23000 (完整性约束违反)，说明是重复的，可以忽略
                if ($e->getCode() !== '23000') {
                    error_log("Database insertion error: " . $e->getMessage());
                } else {
                     echo "Issue {$issue_number} already exists. Skipping.\n";
                }
            }
        }
    }
    // 更新最后处理的 update_id
    $last_update_id = $update['update_id'];
}

// --- 保存最新的 update_id ---
if ($last_update_id > $offset) {
    file_put_contents($offset_file, $last_update_id);
}

echo "Cron job finished.\n";