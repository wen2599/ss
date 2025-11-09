<?php
// File: backend/telegram/receiver.php (Final "Upsert" Logic Version)

ini_set('display_errors', 1);
error_reporting(E_ALL);

define('TELEGRAM_LOG_FILE', __DIR__ . '/telegram_debug.log');
function log_telegram_debug($message) {
    // 为了避免日志文件无限增大，可以在这里增加日志轮转或大小检查逻辑，但暂时保持简单
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, TELEGRAM_LOG_FILE);
}

log_telegram_debug("--- Receiver triggered (POST/Form-Data mode) ---");

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../db_operations.php';
    require_once __DIR__ . '/../lottery/rules.php';
    require_once __DIR__ . '/parser.php';
    log_telegram_debug("Dependencies loaded.");

    $secret = config('TELEGRAM_WEBHOOK_SECRET');
    $received_secret = $_POST['secret'] ?? '';

    if (!$secret || $received_secret !== $secret) {
        log_telegram_debug("FORBIDDEN: Secret mismatch from POST.");
        http_response_code(403);
        exit('Forbidden');
    }
    log_telegram_debug("Security check passed (POST).");

    $base64Data = $_POST['data'] ?? null;
    if (empty($base64Data)) {
        log_telegram_debug("ERROR: 'data' field is missing in POST request.");
        http_response_code(400);
        exit('Bad Request: Missing data.');
    }
    
    $update_json = base64_decode($base64Data);
    $update = json_decode($update_json, true);

    if (isset($update['channel_post']['text'])) {
        log_telegram_debug("Update is a channel post. Calling batch parser...");
        $all_parsed_data = parse_lottery_data_batch($update['channel_post']['text']);
        
        if (!empty($all_parsed_data)) {
            log_telegram_debug("Parser SUCCESS. Found " . count($all_parsed_data) . " results.");
            $pdo = get_db_connection();
            
            // --- 【核心修改】更新 SQL 语句以实现覆盖 ---
            $sql = "
                INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    issue_number = VALUES(issue_number),
                    winning_numbers = VALUES(winning_numbers), 
                    zodiac_signs = VALUES(zodiac_signs), 
                    colors = VALUES(colors), 
                    drawing_date = VALUES(drawing_date),
                    created_at = CURRENT_TIMESTAMP
            ";
            $stmt = $pdo->prepare($sql);
            
            foreach ($all_parsed_data as $parsedData) {
                $stmt->execute([
                    $parsedData['lottery_type'], 
                    $parsedData['issue_number'],
                    json_encode($parsedData['winning_numbers']),
                    json_encode($parsedData['zodiac_signs']),
                    json_encode($parsedData['colors']),
                    $parsedData['drawing_date']
                ]);
            }
            log_telegram_debug("Database upsert successful for " . count($all_parsed_data) . " items.");
        } else {
            log_telegram_debug("Parser did not find any valid lottery data.");
        }
    } else {
        log_telegram_debug("Ignoring update, not a channel post.");
    }

} catch (Throwable $e) {
    log_telegram_debug("--- FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    exit('Internal Server Error');
}

log_telegram_debug("--- Receiver finished successfully ---\n");
http_response_code(200);
echo 'OK';
?>