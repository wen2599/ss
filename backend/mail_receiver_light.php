<?php
// File: mail_receiver_light.php (修改为仅接收，不自动解析)

// --- 独立的日志系统 ---
define('MAIL_LOG_FILE', __DIR__ . '/mail_debug.log');
function log_mail_debug($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, MAIL_LOG_FILE);
}

log_mail_debug("=== 邮件接收器开始 (仅接收模式) ===");

try {
    // --- 1. 加载核心依赖 ---
    require_once __DIR__ . '/config.php';
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

        // --- 6. 自动清理：检查并删除超出10封限制的邮件 ---
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM raw_emails WHERE user_id = ?");
        $stmt_count->execute([$user_id]);
        $email_count = $stmt_count->fetchColumn();
        
        if ($email_count >= 10) {
            log_mail_debug("用户邮件数量: {$email_count}, 开始自动清理...");
            
            // 找出最早的邮件ID进行删除
            $stmt_oldest = $pdo->prepare("
                SELECT id FROM raw_emails 
                WHERE user_id = ? 
                ORDER BY received_at ASC 
                LIMIT 1
            ");
            $stmt_oldest->execute([$user_id]);
            $oldest_email_id = $stmt_oldest->fetchColumn();
            
            if ($oldest_email_id) {
                // 先删除相关的parsed_bets记录（外键约束）
                $stmt_delete_bets = $pdo->prepare("DELETE FROM parsed_bets WHERE email_id = ?");
                $stmt_delete_bets->execute([$oldest_email_id]);
                
                // 再删除邮件记录
                $stmt_delete_email = $pdo->prepare("DELETE FROM raw_emails WHERE id = ?");
                $stmt_delete_email->execute([$oldest_email_id]);
                
                log_mail_debug("已自动删除最旧邮件 ID: " . $oldest_email_id);
            }
        }

        // --- 步骤 A: 存入原始邮件（不自动解析）---
        $stmt_insert_raw = $pdo->prepare("INSERT INTO raw_emails (user_id, content, status) VALUES (?, ?, 'pending')");
        $stmt_insert_raw->execute([$user_id, $raw_content]);
        $email_id = $pdo->lastInsertId();
        log_mail_debug("邮件存入 raw_emails，ID: " . $email_id);

        // 不再自动调用AI解析，等待用户手动解析
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => '邮件接收成功，请手动解析',
            'email_id' => $email_id
        ]);

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
