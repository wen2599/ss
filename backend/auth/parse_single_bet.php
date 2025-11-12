<?php
// File: backend/auth/parse_single_bet.php

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email_id = $input['email_id'] ?? null;
$bet_text = $input['bet_text'] ?? null;
$line_number = $input['line_number'] ?? null;

if (empty($email_id) || empty($bet_text)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email ID and bet text are required.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $user_id = $_SESSION['user_id'];

    // 验证邮件属于当前用户
    $stmt = $pdo->prepare("SELECT id FROM raw_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$email_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
        exit;
    }

    // 获取用户赔率模板
    $stmt_odds = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
    $stmt_odds->execute([$user_id]);
    $userOddsTemplate = $stmt_odds->fetch(PDO::FETCH_ASSOC);

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
        'line_number' => $line_number,
        'bets' => $parse_result['bets'],
        'total_amount' => $parse_result['total_amount'],
        'lottery_type' => $parse_result['lottery_type']
    ]);

    $stmt_insert = $pdo->prepare("
        INSERT INTO parsed_bets (email_id, bet_data_json, ai_model_used, line_number) 
        VALUES (?, ?, 'single_line_parser', ?)
    ");
    $stmt_insert->execute([$email_id, $bet_data_json, $line_number]);

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

/**
 * 解析单条下注文本
 */
function parseSingleBetText(string $text, array $latest_results, ?array $userOddsTemplate = null): array {
    require_once __DIR__ . '/../helpers/manual_parser.php';
    
    // 先尝试手动解析
    $manual_data = parseBetManually($text);
    
    // 如果手动解析没有结果，尝试AI解析
    if (empty($manual_data['bets'])) {
        require_once __DIR__ . '/../ai_helper.php';
        $ai_result = analyzeBetSlipWithAI($text);
        
        if ($ai_result['success'] && isset($ai_result['data'])) {
            $manual_data = $ai_result['data'];
        }
    }

    // 计算结算
    require_once __DIR__ . '/get_email_details.php';
    $settlement_data = calculateManualSettlement($manual_data, $latest_results, $userOddsTemplate);

    return [
        'bets' => $manual_data['bets'] ?? [],
        'total_amount' => $manual_data['total_amount'] ?? 0,
        'lottery_type' => $manual_data['lottery_type'] ?? '混合',
        'settlement' => $settlement_data,
        'raw_text' => $text
    ];
}
?>