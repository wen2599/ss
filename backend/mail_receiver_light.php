<?php
// File: mail_receiver_light.php (完全简化版 - 无频率检查)

// --- 独立的日志系统 ---
define('MAIL_LOG_FILE', __DIR__ . '/mail_debug.log');
function log_mail_debug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, MAIL_LOG_FILE);
}

log_mail_debug("=== 邮件接收器开始 (简化版) ===");

try {
    // --- 1. 加载所有核心依赖 ---
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/ai_helper.php'; 
    log_mail_debug("依赖加载完成");

    // --- 2. 安全验证 (Bearer Token) ---
    $secret = config('EMAIL_WORKER_SECRET');
    if (!$secret) {
        throw new Exception("EMAIL_WORKER_SECRET 未配置");
    }
    
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $auth_header);

    if (!hash_equals($secret, $token)) {
        log_mail_debug("禁止访问: 无效的 token");
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => '禁止访问']);
        exit;
    }
    log_mail_debug("安全检查通过");
    
    // --- 3. 获取并验证输入 (JSON格式) ---
    $json_input = file_get_contents('php://input');
    if (empty($json_input)) {
        throw new Exception("空的 JSON 输入");
    }
    
    $input = json_decode($json_input, true);
    if ($input === null) {
        throw new Exception("无效的 JSON 数据");
    }
    
    $sender_email = $input['sender'] ?? null;
    $raw_content = $input['raw_content'] ?? null;
    
    if (empty($sender_email) || empty($raw_content)) {
        throw new Exception("缺少 'sender' 或 'raw_content' 字段");
    }
    log_mail_debug("收到来自: " . $sender_email . " 的邮件");

    // --- 4. 连接数据库 ---
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
        config('DB_HOST'), config('DB_PORT'), config('DB_DATABASE')
    );
    $options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ];
    $pdo = new PDO($dsn, config('DB_USERNAME'), config('DB_PASSWORD'), $options);
    log_mail_debug("数据库连接成功");

    // --- 5. 查找用户 ---
    $stmt_find = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'active'");
    $stmt_find->execute([$sender_email]);
    $user_id = $stmt_find->fetchColumn();

    if ($user_id) {
        log_mail_debug("找到用户 ID: " . $user_id);

        // --- 步骤 A: 存入原始邮件 ---
        $stmt_insert_raw = $pdo->prepare("INSERT INTO raw_emails (user_id, content, status) VALUES (?, ?, 'pending')");
        $stmt_insert_raw->execute([$user_id, $raw_content]);
        $email_id = $pdo->lastInsertId();
        log_mail_debug("邮件存入 raw_emails，ID: " . $email_id);

        // --- 步骤 B: 调用 AI 分析 ---
        log_mail_debug("调用 AI 分析邮件内容...");
        $ai_result = analyzeBetSlipWithAI($raw_content);

        if ($ai_result['success']) {
            // AI 分析成功
            $model_used = $ai_result['model'] ?? 'unknown_model';
            log_mail_debug("AI 分析成功。模型: " . $model_used);
            $bet_data_json = json_encode($ai_result['data']);

            // 存入 parsed_bets 表
            $stmt_insert_parsed = $pdo->prepare("INSERT INTO parsed_bets (email_id, bet_data_json, ai_model_used) VALUES (?, ?, ?)");
            $stmt_insert_parsed->execute([$email_id, $bet_data_json, $model_used]);
            
            // 更新 raw_emails 状态为 processed
            $stmt_update_status = $pdo->prepare("UPDATE raw_emails SET status = 'processed' WHERE id = ?");
            $stmt_update_status->execute([$email_id]);
            log_mail_debug("解析数据已存储，状态更新为 processed");
            
            http_response_code(200);
            echo json_encode([
                'status' => 'success', 
                'message' => '邮件处理完成', 
                'ai_status' => 'success',
                'email_id' => $email_id,
                'model_used' => $model_used
            ]);
        } else {
            // AI 分析失败
            $error_message = $ai_result['message'] ?? '未知 AI 错误';
            log_mail_debug("AI 分析失败。原因: " . $error_message);
            
            // 更新 raw_emails 状态为 failed
            $stmt_update_status = $pdo->prepare("UPDATE raw_emails SET status = 'failed' WHERE id = ?");
            $stmt_update_status->execute([$email_id]);
            
            http_response_code(200);
            echo json_encode([
                'status' => 'success', 
                'message' => '邮件已保存但 AI 分析失败', 
                'ai_status' => 'failed',
                'email_id' => $email_id,
                'ai_error' => $error_message
            ]);
        }

    } else {
        log_mail_debug("用户 '{$sender_email}' 未找到或未激活。忽略邮件");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => '用户未处理']);
    }

} catch (Throwable $e) {
    log_mail_debug("--- 严重错误 ---");
    log_mail_debug("错误信息: " . $e->getMessage());
    log_mail_debug("文件: " . $e->getFile() . " 第 " . $e->getLine() . " 行");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '内部服务器错误']);
}

log_mail_debug("=== 邮件接收器完成 ===\n");
?>