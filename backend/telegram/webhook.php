<?php // backend/telegram/webhook.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_operations.php';
require_once __DIR__ . '/parser.php';

$secret = getenv('TELEGRAM_WEBHOOK_SECRET');
if (!$secret || ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403); exit('Forbidden');
}

$update = json_decode(file_get_contents('php://input'), true);
if (isset($update['channel_post']['text'])) {
    $parsedData = parse_lottery_data($update['channel_post']['text']);
    if ($parsedData) {
        try {
            $pdo = get_db_connection();
            $sql = "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date) VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE winning_numbers=VALUES(winning_numbers), zodiac_signs=VALUES(zodiac_signs), colors=VALUES(colors), created_at=CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $parsedData['lottery_type'], $parsedData['issue_number'],
                json_encode($parsedData['winning_numbers']),
                json_encode($parsedData['zodiac_signs']),
                json_encode($parsedData['colors']),
                $parsedData['drawing_date']
            ]);
        } catch (PDOException $e) { /* Log error */ }
    }
}
http_response_code(200); echo 'OK';