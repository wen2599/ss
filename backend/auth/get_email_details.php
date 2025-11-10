<?php
// File: backend/auth/get_email_details.php (Upgraded with Auto Lottery Matching)

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
    
    require_once __DIR__ . '/../helpers/mail_parser.php';
    $clean_content = parse_email_body($raw_content);

    // --- 2. 获取所有关联的下注批次 ---
    $stmt_bets = $pdo->prepare("SELECT id, bet_data_json FROM parsed_bets WHERE email_id = ? ORDER BY id ASC");
    $stmt_bets->execute([$email_id]);
    $bet_batches_raw = $stmt_bets->fetchAll(PDO::FETCH_ASSOC);
    
    $bet_batches = array_map(function($batch) {
        return [
            'batch_id' => $batch['id'],
            'data' => json_decode($batch['bet_data_json'], true)
        ];
    }, $bet_batches_raw);

    // --- 3. 【核心新增】获取所有彩种的最新开奖结果 ---
    $sql_latest_results = "
        SELECT r1.*
        FROM lottery_results r1
        JOIN (
            SELECT lottery_type, MAX(id) AS max_id
            FROM lottery_results
            GROUP BY lottery_type
        ) r2 ON r1.lottery_type = r2.lottery_type AND r1.id = r2.max_id
    ";
    $stmt_latest = $pdo->query($sql_latest_results);
    $latest_results_raw = $stmt_latest->fetchAll(PDO::FETCH_ASSOC);
    
    $latest_results = [];
    foreach ($latest_results_raw as $row) {
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
            $row[$key] = json_decode($row[$key]) ?: [];
        }
        $latest_results[$row['lottery_type']] = $row;
    }

    // --- 4. 组合并返回一个包含所有信息的“大礼包” ---
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'email_content' => $clean_content,
            'bet_batches' => $bet_batches,
            'latest_lottery_results' => $latest_results // 将最新开奖结果一并返回
        ]
    ]);

} catch (Throwable $e) {
    error_log("Error in get_email_details.php for email_id {$email_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error.']);
}
?>