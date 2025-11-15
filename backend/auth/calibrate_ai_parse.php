<?php
// File: backend/auth/calibrate_ai_parse.php (修复版)

require_once __DIR__ . '/../ai_helper.php';

// 1. 身份验证
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 2. 获取输入数据
$input = json_decode(file_get_contents('php://input'), true);

// 3. 【核心修复】更严格和清晰的输入验证
$email_id = filter_var($input['email_id'] ?? null, FILTER_VALIDATE_INT);
$line_number = filter_var($input['line_number'] ?? null, FILTER_VALIDATE_INT);
$batch_id = filter_var($input['batch_id'] ?? null, FILTER_VALIDATE_INT);
$correction_data = $input['correction'] ?? null;

if ($email_id === false || $email_id <= 0) {
    http_response_code(400);
    // 向前端返回更具体的信息，方便调试
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing Email ID.']);
    exit;
}
if ($line_number === false || $line_number <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing Line Number.']);
    exit;
}
if ($batch_id === false || $batch_id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing Batch ID.']);
    exit;
}
if (empty($correction_data) || !is_array($correction_data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing correction data.']);
    exit;
}


try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 4. 获取原始解析数据和邮件内容
    $stmt = $pdo->prepare("
        SELECT pb.bet_data_json, re.content AS original_email_content
        FROM parsed_bets pb
        JOIN raw_emails re ON pb.email_id = re.id
        WHERE pb.id = ? AND re.user_id = ? AND pb.line_number = ?
    ");
    $stmt->execute([$batch_id, $user_id, $line_number]);
    $original_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original_data) {
        throw new Exception("Record not found or access denied.", 403);
    }
    $original_parse = json_decode($original_data['bet_data_json'], true);
    $original_text = $original_parse['raw_text'] ?? ''; // 从旧解析中获取单行文本

    // 5. 根据用户的修正，重新构建一个完美的 bet_data_json
    $targets_array = preg_split('/[.,\s]+/', $correction_data['targets']);
    $targets_array = array_values(array_filter($targets_array, 'strlen')); // 确保是连续索引数组

    $amount_value = floatval($correction_data['amount']);
    $total_amount = 0;

    $bet_entry = [
        'bet_type' => $correction_data['bet_type'],
        'targets' => $targets_array,
        'amount' => $amount_value,
        'raw_text' => $original_text,
        'lottery_type' => $original_parse['lottery_type'] ?? '香港六合彩' // 保留原始彩票类型
    ];
    
    // 根据金额模式计算总金额
    if ($correction_data['amount_mode'] === 'per_target') {
        $total_amount = $amount_value * count($targets_array);
    } else {
        $total_amount = $amount_value;
    }
    
    $corrected_parse = [
        'raw_text' => $original_text,
        'line_number' => intval($line_number),
        'bets' => [$bet_entry], // 简化为只包含一个聚合后的bet
        'total_amount' => $total_amount,
        'lottery_type' => $original_parse['lottery_type'] ?? '香港六合彩'
    ];

    // 6. 触发AI学习
    if (function_exists('trainAIWithCorrection')) {
        trainAIWithCorrection([
            'original_text' => $original_text,
            'original_parse' => $original_parse,
            'corrected_parse' => $corrected_parse,
            'correction_reason' => $correction_data['reason'] ?? ''
        ]);
    }

    // 7. 更新数据库中的 bet_data_json
    $stmt_update = $pdo->prepare("UPDATE parsed_bets SET bet_data_json = ? WHERE id = ?");
    $stmt_update->execute([json_encode($corrected_parse), $batch_id]);

    // 8. 重新结算
    require_once __DIR__ . '/get_email_details.php'; // 引入结算函数
    
    // 获取最新开奖结果和赔率模板
    $stmt_odds = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
    $stmt_odds->execute([$user_id]);
    $userOddsTemplate = $stmt_odds->fetch(PDO::FETCH_ASSOC);

    $sql_latest_results = "SELECT r1.* FROM lottery_results r1 JOIN (SELECT lottery_type, MAX(id) AS max_id FROM lottery_results GROUP BY lottery_type) r2 ON r1.lottery_type = r2.lottery_type AND r1.id = r2.max_id";
    $stmt_latest = $pdo->query($sql_latest_results);
    $latest_results_raw = $stmt_latest->fetchAll(PDO::FETCH_ASSOC);

    $latest_results = [];
    foreach ($latest_results_raw as $row) {
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
            $row[$key] = json_decode($row[$key], true) ?: [];
        }
        $latest_results[$row['lottery_type']] = $row;
    }
    
    $lottery_result_for_settlement = $latest_results[$corrected_parse['lottery_type']] ?? null;
    $settlement_data = calculateBatchSettlement($corrected_parse, $lottery_result_for_settlement, $userOddsTemplate);
    $corrected_parse['settlement'] = $settlement_data;

    // 9. 提交事务
    $pdo->commit();

    // 10. 返回包含最新解析和结算的完整数据
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'AI校准成功并已重新结算！',
        'data' => [
            'batch_id' => $batch_id,
            // 【重要】返回的结构要和 parse_single_bet 一致
            'parse_result' => $corrected_parse,
            'line_number' => intval($line_number)
        ]
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("AI Calibration Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code($e->getCode() == 403 ? 403 : 500);
    echo json_encode(['status' => 'error', 'message' => '校准失败: ' . $e->getMessage()]);
}
?>