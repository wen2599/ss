<?php
// File: backend/auth/quick_calibrate_ai.php

require_once __DIR__ . '/../ai_helper.php';

// 1. 身份验证
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. 获取并严格验证输入
$input = json_decode(file_get_contents('php://input'), true);
$email_id = filter_var($input['email_id'] ?? null, FILTER_VALIDATE_INT);
$line_number = filter_var($input['line_number'] ?? null, FILTER_VALIDATE_INT);
$batch_id = filter_var($input['batch_id'] ?? null, FILTER_VALIDATE_INT);
$corrected_total_amount = filter_var($input['corrected_total_amount'] ?? null, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$reason = trim($input['reason'] ?? '');

if (!$email_id || !$line_number || !$batch_id || $corrected_total_amount === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid required parameters.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 3. 获取原始解析数据和单行文本
    $stmt = $pdo->prepare("
        SELECT bet_data_json FROM parsed_bets pb
        JOIN raw_emails re ON pb.email_id = re.id
        WHERE pb.id = ? AND re.user_id = ?
    ");
    $stmt->execute([$batch_id, $user_id]);
    $original_bet_json = $stmt->fetchColumn();

    if (!$original_bet_json) {
        throw new Exception("Record not found or access denied.", 403);
    }
    $original_parse = json_decode($original_bet_json, true);
    $original_text = $original_parse['raw_text'] ?? '';
    $lottery_type = $original_parse['lottery_type'] ?? '香港六合彩';

    // 4. 调用AI进行带有“提示”的重新解析
    $ai_result = analyzeSingleBetWithAI($original_text, $lottery_type, [
        'original_parse' => $original_parse,
        'corrected_total_amount' => $corrected_total_amount,
        'reason' => $reason
    ]);

    if (!$ai_result['success'] || empty($ai_result['data'])) {
        throw new Exception($ai_result['message'] ?? 'AI re-parsing failed.');
    }

    $new_parse_data = $ai_result['data'];
    $new_parse_data['line_number'] = $line_number; // 确保line_number在数据中
    $new_parse_data['raw_text'] = $original_text; // 确保原始文本在数据中

    // 5. 重新结算
    require_once __DIR__ . '/get_email_details.php';
    $stmt_odds = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
    $stmt_odds->execute([$user_id]);
    $userOddsTemplate = $stmt_odds->fetch(PDO::FETCH_ASSOC);

    $sql_latest_results = "SELECT r1.* FROM lottery_results r1 JOIN (SELECT lottery_type, MAX(id) AS max_id FROM lottery_results GROUP BY lottery_type) r2 ON r1.lottery_type = r2.lottery_type AND r1.id = r2.max_id";
    $stmt_latest = $pdo->query($sql_latest_results);
    $latest_results_raw = $stmt_latest->fetchAll(PDO::FETCH_ASSOC);
    $latest_results = [];
    foreach ($latest_results_raw as $row) {
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) { $row[$key] = json_decode($row[$key], true) ?: []; }
        $latest_results[$row['lottery_type']] = $row;
    }
    
    $lottery_result_for_settlement = $latest_results[$new_parse_data['lottery_type']] ?? null;
    $settlement_data = calculateBatchSettlement($new_parse_data, $lottery_result_for_settlement, $userOddsTemplate);
    $new_parse_data['settlement'] = $settlement_data;

    // 6. 更新数据库
    $stmt_update = $pdo->prepare("UPDATE parsed_bets SET bet_data_json = ? WHERE id = ?");
    $stmt_update->execute([json_encode($new_parse_data), $batch_id]);

    // 7. 提交事务
    $pdo->commit();

    // 8. 返回成功响应
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'AI已根据您的提示重新解析并结算！',
        'data' => [
            'batch_id' => $batch_id,
            'parse_result' => $new_parse_data,
            'line_number' => intval($line_number)
        ]
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Quick Calibration Error: " . $e->getMessage());
    http_response_code($e->getCode() == 403 ? 403 : 500);
    echo json_encode(['status' => 'error', 'message' => '快速校准失败: ' . $e->getMessage()]);
}
?>