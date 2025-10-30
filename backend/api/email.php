<?php
require_once '../db.php';
require_once '../functions.php';

// Include ai.php to call its functions
require_once __DIR__ . '/ai.php';

function insertEmail() {
    global $pdo;
    $data = json_decode(file_get_contents('php://input'), true);  // 从 Workers POST
    $body = $data['body'];
    $subject = $data['subject'];
    $from = $data['from'];

    // 检查 .env 是否已加载
    global $dotenv; 
    if (!isset($dotenv)) {
        $dotenv = parse_ini_file('../.env');
    }

    // 检查 subject 是否含 token (注册验证)
    if (strpos($subject, 'token:') === 0) {
        $token = substr($subject, 6);
        // 验证 token，创建用户
        $stmt = $pdo->prepare("SELECT email FROM temp_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $email = $stmt->fetchColumn();
        if ($email) {
            $pdo->prepare("INSERT INTO users (email) VALUES (?)")->execute([$email]);
            $userId = $pdo->lastInsertId();
            $pdo->prepare("DELETE FROM temp_tokens WHERE token = ?")->execute([$token]);
        } else {
            json_error('Invalid token');
        }
    } else {
        // 正常邮件，找用户 (from_email 匹配 users.email 或 authorized_emails)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? UNION SELECT user_id FROM authorized_emails WHERE email = ?");
        $stmt->execute([$from, $from]);
        $userId = $stmt->fetchColumn() ?: null;  // 如果无用户，存 null
    }
    $stmt = $pdo->prepare("INSERT INTO emails (user_id, subject, body, from_email) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $subject, $body, $from]);
    echo json_encode(['success' => true]);

    // 如果是授权邮箱，自动触发识别
    $emailId = $pdo->lastInsertId();
    if ($pdo->query("SELECT COUNT(*) FROM authorized_emails WHERE email = '" . $pdo->quote($from) . "'")->fetchColumn() > 0) {
        recognize($emailId);  // Directly call the recognize function from ai.php
    }
}

function getEmails() {
    global $pdo;
    // Assuming JWT is passed in Authorization header for authentication
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        json_error('Authorization header missing', 401);
    }
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $userData = validateJWT($token);

    if (!$userData) {
        json_error('Invalid or expired token', 401);
    }
    $userId = $userData['id'];

    $stmt = $pdo->prepare("SELECT * FROM emails WHERE user_id = ? ORDER BY received_at DESC");
    $stmt->execute([$userId]);
    echo json_encode($stmt->fetchAll());
}