<?php
// File: backend/auth/parse_single_bet.php (修复参数验证)

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email_id = $input['email_id'] ?? null;
$bet_text = $input['bet_text'] ?? null;
$line_number = $input['line_number'] ?? null;

// 更严格的参数验证
if (empty($email_id) || !is_numeric($email_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid Email ID is required.']);
    exit;
}

if (empty($bet_text) || !is_string($bet_text)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid bet text is required.']);
    exit;
}

// line_number 可以为空，但如果有值必须是数字
if ($line_number !== null && !is_numeric($line_number)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Line number must be numeric.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $user_id = $_SESSION['user_id'];

    // 验证邮件属于当前用户
    $stmt = $pdo->prepare("SELECT id FROM raw_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([intval($email_id), $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Email not found.']);
        exit;
    }

    // 获取用户赔率模板
    $stmt_odds = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
    $stmt_odds->execute([$user_id]);
    $userOddsTemplate = $stmt_odds->fetch(PDO::FETCH_ASSOC);

    // 如果没有赔率模板，设置为空数组
    if (!$userOddsTemplate) {
        $userOddsTemplate = [];
    }

    // 获取最新开奖结果
    $sql_latest_results = "
        SELECT r1.* FROM lottery_results r1
        JOIN (SELECT lottery_type, MAX(id) AS max_id FROM lottery_results GROUP BY lottery_type) r2 
        ON r1.lottery_type = r2.lottery_type AND r1.id = r2.max_id
    ";
    $stmt_latest = $pdo->query($sql_latest_results);
    $latest_results_raw = $stmt_latest->fetchAll(PDO::FETCH_ASSOC);

    $latest_results = [];
    foreach ($latest_results_raw as $row) {
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
            $decoded = json_decode($row[$key], true);
            $row[$key] = $decoded ?: [];
        }
        $latest_results[$row['lottery_type']] = $row;
    }

    // 解析单条下注
    $parse_result = parseSingleBetText($bet_text, $latest_results, $userOddsTemplate);

    // 保存到数据库
    $bet_data_json = json_encode([
        'raw_text' => $bet_text,
        'line_number' => $line_number ? intval($line_number) : null,
        'bets' => $parse_result['bets'],
        'total_amount' => $parse_result['total_amount'],
        'lottery_type' => $parse_result['lottery_type'],
        'settlement' => $parse_result['settlement']
    ]);

    $stmt_insert = $pdo->prepare("
        INSERT INTO parsed_bets (email_id, bet_data_json, ai_model_used, line_number) 
        VALUES (?, ?, 'single_line_parser', ?)
    ");
    $stmt_insert->execute([
        intval($email_id), 
        $bet_data_json, 
        $line_number ? intval($line_number) : null
    ]);

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'batch_id' => $pdo->lastInsertId(),
            'parse_result' => $parse_result,
            'line_number' => $line_number
        ]
    ]);

} catch (Throwable $e) {
    error_log("Error parsing single bet: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '解析失败: ' . $e->getMessage()]);
}

// parseSingleBetText 函数保持不变...
?>