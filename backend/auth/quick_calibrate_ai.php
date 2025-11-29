<?php
// File: backend/auth/quick_calibrate_ai.php

// 启用调试日志
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../debug.log');

try {
    // 1. 加载核心依赖
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../db_operations.php';
    require_once __DIR__ . '/../ai_helper.php';

    // 2. 身份验证
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    $user_id = $_SESSION['user_id'];

    // 3. 获取并验证输入
    $input_json = file_get_contents('php://input');
    
    // 【调试】记录日志，查看是否接收到了数据
    error_log("QuickCalibrate Recv Length: " . strlen($input_json));
    
    $input = json_decode($input_json, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // 如果 JSON 解析失败，抛出详细错误
        throw new Exception("JSON Decode Error: " . json_last_error_msg() . " (Raw Length: " . strlen($input_json) . ")", 400);
    }
    
    if (!$input) {
        throw new Exception("接收到的请求体为空 (Body is empty)", 400);
    }

    // 4. 参数提取
    $email_id = isset($input['email_id']) ? intval($input['email_id']) : 0;
    $line_number = isset($input['line_number']) ? intval($input['line_number']) : 0;
    $batch_id = isset($input['batch_id']) ? intval($input['batch_id']) : 0;
    $corrected_total_amount = isset($input['corrected_total_amount']) ? floatval($input['corrected_total_amount']) : 0;
    $reason = trim($input['reason'] ?? '');

    // 5. 必需参数验证
    if ($email_id <= 0) {
        error_log("Missing email_id. Received keys: " . implode(',', array_keys($input)));
        throw new Exception("Email ID is required (Received: " . ($input['email_id'] ?? 'null') . ")", 400);
    }
    if ($line_number <= 0) throw new Exception("Line Number is required", 400);
    if ($batch_id <= 0) throw new Exception("Batch ID is required", 400);

    // 6. 数据库操作
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 验证记录所有权
    $stmt = $pdo->prepare("
        SELECT pb.bet_data_json, pb.email_id, re.user_id
        FROM parsed_bets pb
        JOIN raw_emails re ON pb.email_id = re.id
        WHERE pb.id = ? AND re.user_id = ? AND re.id = ?
    ");
    $stmt->execute([$batch_id, $user_id, $email_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        throw new Exception("找不到该记录或无权访问", 404);
    }

    $original_parse = json_decode($result['bet_data_json'], true);
    $original_text = $original_parse['raw_text'] ?? '';
    $lottery_type = $original_parse['lottery_type'] ?? '香港六合彩';

    // 7. 调用 AI 重新解析
    $ai_result = analyzeSingleBetWithAI($original_text, $lottery_type, [
        'original_parse' => $original_parse,
        'corrected_total_amount' => $corrected_total_amount,
        'reason' => $reason
    ]);

    if (!$ai_result['success']) {
        throw new Exception("AI 重新解析失败: " . ($ai_result['message'] ?? '未知错误'), 500);
    }

    $new_parse_data = $ai_result['data'];
    $new_parse_data['line_number'] = $line_number;
    $new_parse_data['raw_text'] = $original_text;

    // 强制修正总金额
    if (abs($new_parse_data['total_amount'] - $corrected_total_amount) > 0.01) {
        $new_parse_data['total_amount'] = $corrected_total_amount;
    }

    // 8. 重新结算
    if (!function_exists('calculateBatchSettlement')) {
        require_once __DIR__ . '/get_email_details.php';
    }

    $stmt_odds = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
    $stmt_odds->execute([$user_id]);
    $userOddsTemplate = $stmt_odds->fetch(PDO::FETCH_ASSOC) ?: [];

    // 获取开奖结果
    $stmt_latest = $pdo->query("SELECT * FROM lottery_results ORDER BY id DESC LIMIT 50"); 
    $latest_results_raw = $stmt_latest->fetchAll(PDO::FETCH_ASSOC);
    $latest_results = [];
    foreach ($latest_results_raw as $row) {
        $type = $row['lottery_type'];
        if (!isset($latest_results[$type])) {
            foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
                $decoded = json_decode($row[$key], true);
                $row[$key] = $decoded ?: [];
            }
            $latest_results[$type] = $row;
        }
    }

    $lottery_result_for_settlement = $latest_results[$new_parse_data['lottery_type']] ?? null;
    $settlement_data = calculateBatchSettlement($new_parse_data, $lottery_result_for_settlement, $userOddsTemplate);
    $new_parse_data['settlement'] = $settlement_data;

    // 9. 更新数据库
    $new_bet_json = json_encode($new_parse_data);
    $stmt_update = $pdo->prepare("UPDATE parsed_bets SET bet_data_json = ? WHERE id = ?");
    $stmt_update->execute([$new_bet_json, $batch_id]);

    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'AI已根据您的提示重新解析并结算！',
        'data' => [
            'batch_id' => $batch_id,
            'parse_result' => $new_parse_data,
            'line_number' => $line_number
        ]
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Quick Calibrate Error: " . $e->getMessage());
    
    $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>