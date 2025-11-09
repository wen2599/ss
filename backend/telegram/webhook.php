<?php
// File: backend/telegram/webhook.php (Updated for Batch Processing)

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_operations.php';
require_once __DIR__ . '/parser.php'; // 加载新的批量解析器

// ... (安全验证和日志等代码保持不变) ...

$secret = config('TELEGRAM_WEBHOOK_SECRET');
if (!$secret || ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403); exit('Forbidden');
}

$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['channel_post']['text'])) {
    
    // 【核心修改】调用批量解析函数
    $all_parsed_data = parse_lottery_data_batch($update['channel_post']['text']);
    
    if (!empty($all_parsed_data)) {
        try {
            $pdo = get_db_connection();
            
            // 准备 SQL 语句
            $sql = "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date) VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE winning_numbers=VALUES(winning_numbers), zodiac_signs=VALUES(zodiac_signs), colors=VALUES(colors), created_at=CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);

            // 【核心修改】循环处理所有解析出的结果
            foreach ($all_parsed_data as $parsedData) {
                $stmt->execute([
                    $parsedData['lottery_type'], $parsedData['issue_number'],
                    json_encode($parsedData['winning_numbers']),
                    json_encode($parsedData['zodiac_signs']),
                    json_encode($parsedData['colors']),
                    $parsedData['drawing_date']
                ]);
                // 可以加日志记录每次插入
            }
        } catch (PDOException $e) {
            error_log("Telegram webhook DB error: " . $e->getMessage());
        }
    }
}

http_response_code(200);
echo 'OK';