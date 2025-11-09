<?php
// File: backend/auth/get_email_details.php

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

    // --- 1. 获取原始邮件内容 (清洗后) ---
    $stmt_email = $pdo->prepare("SELECT content FROM raw_emails WHERE id = ? AND user_id = ?");
    $stmt_email->execute([$email_id, $user_id]);
    $raw_content = $stmt_email->fetchColumn();

    if ($raw_content === false) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Email not found or access denied.']);
        exit;
    }
    
    // 引入解析器并获取干净的邮件正文
    require_once __DIR__ . '/../helpers/mail_parser.php';
    $clean_content = parse_email_body($raw_content);

    // --- 2. 获取所有关联的、AI已解析的下注批次 ---
    $stmt_bets = $pdo->prepare("SELECT id, bet_data_json FROM parsed_bets WHERE email_id = ? ORDER BY id ASC");
    $stmt_bets->execute([$email_id]);
    $bet_batches_raw = $stmt_bets->fetchAll(PDO::FETCH_ASSOC);
    
    // 解码 JSON
    $bet_batches = array_map(function($batch) {
        return [
            'batch_id' => $batch['id'],
            'data' => json_decode($batch['bet_data_json'], true)
        ];
    }, $bet_batches_raw);

    // --- 3. 组合并返回数据 ---
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'email_content' => $clean_content,
            'bet_batches' => $bet_batches
        ]
    ]);

} catch (Throwable $e) {
    error_log("Error in get_email_details.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error.']);
}
?>