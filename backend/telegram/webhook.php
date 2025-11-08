<?php
// File: backend/telegram/webhook.php (Ultimate Debugging Version)

// --- 强制开启错误显示，并将错误记录到日志中 ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- 独立的日志系统，不依赖其他文件 ---
define('TELEGRAM_LOG_FILE', __DIR__ . '/telegram_debug.log');

function log_telegram_debug($message) {
    // 使用 error_log 函数，这是最可靠的写入文件的方式
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, TELEGRAM_LOG_FILE);
}

// 脚本开始执行的第一条日志
log_telegram_debug("--- Webhook triggered ---");
log_telegram_debug("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
log_telegram_debug("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

try {
    // --- 加载依赖 ---
    require_once __DIR__ . '/../config.php';
    log_telegram_debug("config.php loaded.");
    
    require_once __DIR__ . '/../db_operations.php';
    log_telegram_debug("db_operations.php loaded.");

    require_once __DIR__ . '/parser.php';
    log_telegram_debug("parser.php loaded.");

    // --- 1. 安全验证 ---
    log_telegram_debug("Performing security check...");
    $secret = config('TELEGRAM_WEBHOOK_SECRET');
    $received_secret = $_GET['secret'] ?? '';

    if (!$secret) {
        log_telegram_debug("FATAL: TELEGRAM_WEBHOOK_SECRET is not set in .env file. Aborting.");
        http_response_code(500);
        exit('Internal Configuration Error');
    }

    if ($received_secret !== $secret) {
        log_telegram_debug("FORBIDDEN: Secret mismatch. Expected: [{$secret}], Received: [{$received_secret}]. Aborting.");
        http_response_code(403);
        exit('Forbidden');
    }
    log_telegram_debug("Security check passed.");

    // --- 2. 获取并解析输入 ---
    log_telegram_debug("Reading php://input...");
    $update_json = file_get_contents('php://input');
    if ($update_json === false || empty($update_json)) {
        log_telegram_debug("WARNING: php://input was empty or failed to read. Exiting.");
        http_response_code(200); // 告诉 Telegram 没事了，不要重试
        exit('OK');
    }
    log_telegram_debug("Raw Input JSON: " . $update_json);
    
    $update = json_decode($update_json, true);

    // --- 3. 检查是否为频道消息 ---
    if (!isset($update['channel_post']['text'])) {
        log_telegram_debug("Update is not a channel post with text. Ignoring. Update type: " . array_keys($update)[1]);
        http_response_code(200);
        exit('OK');
    }
    log_telegram_debug("Update is a channel post. Processing text...");
    
    // --- 4. 调用解析器 ---
    $message_text = $update['channel_post']['text'];
    log_telegram_debug("Parsing text: " . str_replace("\n", " ", $message_text));
    $parsedData = parse_lottery_data($message_text);

    if ($parsedData) {
        log_telegram_debug("Parser SUCCESS. Data: " . json_encode($parsedData, JSON_UNESCAPED_UNICODE));
        
        // --- 5. 写入数据库 ---
        log_telegram_debug("Connecting to database to store result...");
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
        log_telegram_debug("Database write successful for issue: " . $parsedData['issue_number']);

    } else {
        log_telegram_debug("Parser FAILED. No data to store.");
    }

} catch (Throwable $e) {
    // 如果整个过程中有任何致命错误或异常，记录下来
    log_telegram_debug("--- FATAL ERROR / EXCEPTION CAUGHT ---");
    log_telegram_debug("Error Type: " . get_class($e));
    log_telegram_debug("Message: " . $e->getMessage());
    log_telegram_debug("File: " . $e->getFile());
    log_telegram_debug("Line: " . $e->getLine());
    http_response_code(500); // 向 Telegram 返回服务器错误
    exit('Internal Server Error');
}

// --- 正常结束 ---
log_telegram_debug("--- Webhook finished successfully ---\n");
http_response_code(200);
echo 'OK';
?>