<?php
// File: backend/auth/get_email_content.php (with Server-Side Parsing)

// 核心依赖由 index.php 加载

// 【新增】加载邮件解析器帮助文件
// 确保这个路径是正确的。如果你的 helpers 目录在 backend/ 根目录下。
require_once __DIR__ . '/../helpers/mail_parser.php';

// 1. 身份验证检查
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$email_id = $_GET['id'] ?? null;

if (empty($email_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email ID is required.']);
    exit;
}

try {
    $pdo = get_db_connection();

    // 2. 查询特定邮件的原始内容
    $stmt = $pdo->prepare(
        "SELECT id, content 
         FROM raw_emails 
         WHERE id = ? AND user_id = ?"
    );
    
    $stmt->bindParam(1, $email_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($email) {
        // --- 【核心修改】在这里进行邮件解析 ---
        $raw_content = $email['content'];
        $clean_body = parse_email_body($raw_content);

        // 创建一个新的数组，只包含我们想返回给前端的数据
        $response_data = [
            'id' => $email['id'],
            'content' => $clean_body // 返回解析后的干净正文
        ];
        
        // 3. 返回包含干净正文的成功响应
        http_response_code(200);
        echo json_encode(['status' => 'success', 'data' => $response_data]);

    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Email not found or access denied.']);
    }

} catch (PDOException $e) {
    error_log("Error fetching email content for user {$user_id}, email {$email_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error.']);
} catch (Throwable $e) {
    // 捕获可能在 parse_email_body 中发生的错误
    error_log("Error parsing email content for email {$email_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to parse email content on server.']);
}
?>