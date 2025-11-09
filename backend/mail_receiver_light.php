<?php
// File: mail_receiver_light.php (Lightweight Version with AI Integration and Pre-parsing)

// --- 独立的日志系统 ---
define('MAIL_LOG_FILE', __DIR__ . '/mail_debug.log');
function log_mail_debug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, MAIL_LOG_FILE);
}

log_mail_debug("--- Mail receiver triggered ---");

try {
    // --- 1. 加载所有核心依赖 ---
    // config.php 是基础，定义了 config() 函数
    require_once __DIR__ . '/config.php';
    // ai_helper.php 定义了 AI 分析函数，并会自动包含其依赖的 mail_parser.php
    require_once __DIR__ . '/ai_helper.php'; 
    log_mail_debug("Core dependencies loaded (config.php, ai_helper.php).");

    // --- 2. 安全验证 ---
    $secret = config('EMAIL_WORKER_SECRET');
    if (!$secret) {
        throw new Exception("EMAIL_WORKER_SECRET not found in config.");
    }
    
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $auth_header);

    if (!hash_equals($secret, $token)) {
        log_mail_debug("Forbidden: Invalid token provided.");
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
    log_mail_debug("Security check passed.");
    
    // --- 3. 获取并验证输入 ---
    $input = json_decode(file_get_contents('php://input'), true);
    $sender_email = $input['sender'] ?? null;
    $raw_content = $input['raw_content'] ?? null;
    
    if (empty($sender_email) || empty($raw_content)) {
        throw new Exception("Missing 'sender' or 'raw_content' in JSON payload.");
    }
    log_mail_debug("Received email from: " . $sender_email);

    // --- 4. 连接数据库 ---
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
        config('DB_HOST'), config('DB_PORT'), config('DB_DATABASE')
    );
    $options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ];
    $pdo = new PDO($dsn, config('DB_USERNAME'), config('DB_PASSWORD'), $options);
    log_mail_debug("Database connection successful.");

    // --- 5. 查找用户 ---
    $stmt_find = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'active'");
    $stmt_find->execute([$sender_email]);
    $user_id = $stmt_find->fetchColumn();

    if ($user_id) {
        log_mail_debug("User found with ID: " . $user_id);

        // --- 步骤 A: 存入原始邮件 ---
        $stmt_insert_raw = $pdo->prepare("INSERT INTO raw_emails (user_id, content, status) VALUES (?, ?, 'pending')");
        $stmt_insert_raw->execute([$user_id, $raw_content]);
        $email_id = $pdo->lastInsertId();
        log_mail_debug("Email inserted into raw_emails with ID: " . $email_id);

        // --- 步骤 B: 立即调用 AI 进行分析 ---
        log_mail_debug("Calling AI to analyze email content (ID: {$email_id})...");
        $ai_result = analyzeBetSlipWithAI($raw_content);

        if ($ai_result['success']) {
            // AI 分析成功
            $model_used = $ai_result['model'] ?? 'unknown_model';
            log_mail_debug("AI analysis successful. Model: " . $model_used);
            $bet_data_json = json_encode($ai_result['data']);

            // 存入 parsed_bets 表
            $stmt_insert_parsed = $pdo->prepare("INSERT INTO parsed_bets (email_id, bet_data_json, ai_model_used) VALUES (?, ?, ?)");
            $stmt_insert_parsed->execute([$email_id, $bet_data_json, $model_used]);
            
            // 更新 raw_emails 状态为 processed
            $stmt_update_status = $pdo->prepare("UPDATE raw_emails SET status = 'processed' WHERE id = ?");
            $stmt_update_status->execute([$email_id]);
            log_mail_debug("Parsed bets stored and raw_email status updated to 'processed'.");
        } else {
            // AI 分析失败
            $error_message = $ai_result['message'] ?? 'Unknown AI error';
            log_mail_debug("AI analysis FAILED. Reason: " . $error_message);
            // 如果有原始响应，也记录下来
            if (isset($ai_result['raw_response'])) {
                log_mail_debug("AI Raw Response: " . $ai_result['raw_response']);
            }
            // 更新 raw_emails 状态为 failed
            $stmt_update_status = $pdo->prepare("UPDATE raw_emails SET status = 'failed' WHERE id = ?");
            $stmt_update_status->execute([$email_id]);
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Email processed.', 'ai_status' => $ai_result['success'] ? 'success' : 'failed']);

    } else {
        log_mail_debug("User '{$sender_email}' not found or inactive. Ignoring email.");
        http_response_code(200); // 仍然返回200，防止Worker重试
        echo json_encode(['status' => 'success', 'message' => 'User not processed.']);
    }

} catch (Throwable $e) {
    log_mail_debug("--- FATAL ERROR / EXCEPTION CAUGHT ---");
    log_mail_debug("Message: " . $e->getMessage());
    log_mail_debug("File: " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error.']);
}

log_mail_debug("--- Mail receiver finished ---\n");
?>