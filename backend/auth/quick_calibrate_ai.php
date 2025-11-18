<?php
// File: backend/auth/quick_calibrate_ai.php (完全重写版)

// 启用详细错误日志
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../debug.log');

// 记录请求开始
error_log("=== Quick Calibration Request Started ===");

try {
    // 1. 加载核心依赖
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../db_operations.php';
    require_once __DIR__ . '/../ai_helper.php';
    
    error_log("Dependencies loaded successfully");

    // 2. 身份验证
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized - User not logged in", 401);
    }
    $user_id = $_SESSION['user_id'];
    error_log("User authenticated: " . $user_id);

    // 3. 获取并验证输入
    $input_json = file_get_contents('php://input');
    error_log("Raw input received: " . $input_json);

    if (empty($input_json)) {
        throw new Exception("No input data received", 400);
    }

    $input = json_decode($input_json, true);
    if ($input === null) {
        throw new Exception("Invalid JSON input: " . json_last_error_msg(), 400);
    }

    error_log("Parsed input: " . print_r($input, true));

    // 4. 参数验证 - 更严格的验证
    $email_id = isset($input['email_id']) ? intval($input['email_id']) : 0;
    $line_number = isset($input['line_number']) ? intval($input['line_number']) : 0;
    $batch_id = isset($input['batch_id']) ? intval($input['batch_id']) : 0;
    $corrected_total_amount = isset($input['corrected_total_amount']) ? floatval($input['corrected_total_amount']) : 0;
    $reason = trim($input['reason'] ?? '');

    error_log("Validated parameters - email_id: {$email_id}, line_number: {$line_number}, batch_id: {$batch_id}, amount: {$corrected_total_amount}");

    // 检查必需参数
    if ($email_id <= 0) {
        throw new Exception("Valid Email ID is required", 400);
    }
    if ($line_number <= 0) {
        throw new Exception("Valid Line Number is required", 400);
    }
    if ($batch_id <= 0) {
        throw new Exception("Valid Batch ID is required", 400);
    }
    if ($corrected_total_amount <= 0) {
        throw new Exception("Valid corrected total amount is required", 400);
    }

    error_log("All parameters validated successfully");

    // 5. 数据库操作
    $pdo = get_db_connection();
    error_log("Database connection established");

    $pdo->beginTransaction();
    error_log("Transaction started");

    // 6. 验证记录所有权和获取原始数据
    $stmt = $pdo->prepare("
        SELECT pb.bet_data_json, pb.email_id, re.user_id
        FROM parsed_bets pb
        JOIN raw_emails re ON pb.email_id = re.id
        WHERE pb.id = ? AND re.user_id = ? AND re.id = ?
    ");
    $stmt->execute([$batch_id, $user_id, $email_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        throw new Exception("Record not found or access denied. Batch ID: $batch_id, User ID: $user_id, Email ID: $email_id", 404);
    }

    error_log("Original bet data retrieved and ownership verified");

    $original_bet_json = $result['bet_data_json'];
    $original_parse = json_decode($original_bet_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode original bet data: " . json_last_error_msg(), 500);
    }

    $original_text = $original_parse['raw_text'] ?? '';
    $lottery_type = $original_parse['lottery_type'] ?? '香港六合彩';

    error_log("Original text: " . substr($original_text, 0, 100));
    error_log("Lottery type: " . $lottery_type);

    // 7. 调用AI重新解析
    error_log("Calling AI for re-parsing...");
    $ai_result = analyzeSingleBetWithAI($original_text, $lottery_type, [
        'original_parse' => $original_parse,
        'corrected_total_amount' => $corrected_total_amount,
        'reason' => $reason
    ]);

    error_log("AI result success: " . ($ai_result['success'] ? 'true' : 'false'));

    if (!$ai_result['success']) {
        throw new Exception("AI re-parsing failed: " . ($ai_result['message'] ?? 'Unknown error'), 500);
    }

    if (empty($ai_result['data'])) {
        throw new Exception("AI returned empty data", 500);
    }

    // 8. 准备新的解析数据
    $new_parse_data = $ai_result['data'];
    $new_parse_data['line_number'] = $line_number;
    $new_parse_data['raw_text'] = $original_text;

    // 确保总金额正确
    if (abs($new_parse_data['total_amount'] - $corrected_total_amount) > 0.01) {
        error_log("Warning: AI returned amount {$new_parse_data['total_amount']} doesn't match corrected amount {$corrected_total_amount}");
        $new_parse_data['total_amount'] = $corrected_total_amount;
    }

    error_log("New parse data prepared with total amount: " . $new_parse_data['total_amount']);

    // 9. 重新结算
    error_log("Starting re-settlement...");

    // 引入结算函数
    if (!function_exists('calculateBatchSettlement')) {
        require_once __DIR__ . '/get_email_details.php';
    }

    // 获取用户赔率模板
    $stmt_odds = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
    $stmt_odds->execute([$user_id]);
    $userOddsTemplate = $stmt_odds->fetch(PDO::FETCH_ASSOC);

    if (!$userOddsTemplate) {
        $userOddsTemplate = [];
        error_log("No odds template found for user, using empty template");
    }

    // 获取最新开奖结果
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
            $decoded = json_decode($row[$key], true);
            $row[$key] = $decoded ?: [];
        }
        $latest_results[$row['lottery_type']] = $row;
    }

    $lottery_result_for_settlement = $latest_results[$new_parse_data['lottery_type']] ?? null;

    if (!$lottery_result_for_settlement) {
        error_log("Warning: No lottery result found for type: " . $new_parse_data['lottery_type']);
    }

    // 计算结算
    $settlement_data = calculateBatchSettlement($new_parse_data, $lottery_result_for_settlement, $userOddsTemplate);
    $new_parse_data['settlement'] = $settlement_data;

    error_log("Settlement calculated successfully");

    // 10. 更新数据库
    $new_bet_json = json_encode($new_parse_data);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to encode new bet data: " . json_last_error_msg(), 500);
    }

    $stmt_update = $pdo->prepare("UPDATE parsed_bets SET bet_data_json = ? WHERE id = ?");
    $stmt_update->execute([$new_bet_json, $batch_id]);

    $affected_rows = $stmt_update->rowCount();
    error_log("Database updated, affected rows: " . $affected_rows);

    // 11. 提交事务
    $pdo->commit();
    error_log("Transaction committed successfully");

    // 12. 返回成功响应
    $response = [
        'status' => 'success',
        'message' => 'AI已根据您的提示重新解析并结算！',
        'data' => [
            'batch_id' => $batch_id,
            'parse_result' => $new_parse_data,
            'line_number' => $line_number
        ]
    ];

    error_log("Sending success response");
    http_response_code(200);
    echo json_encode($response);

} catch (Throwable $e) {
    // 错误处理
    error_log("Quick Calibration Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // 回滚事务
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transaction rolled back");
    }

    $status_code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;

    $error_response = [
        'status' => 'error',
        'message' => '快速校准失败: ' . $e->getMessage()
    ];

    http_response_code($status_code);
    echo json_encode($error_response);
}

error_log("=== Quick Calibration Request Completed ===");
?>