<?php
// File: backend/auth/quick_calibrate_ai.php (修复路径和事务问题)

// 启用详细错误日志
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../debug.log');

// 记录请求开始
error_log("=== Quick Calibration Request Started ===");

try {
    // 1. 加载依赖 - 使用相对路径
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

    // 验证必需参数
    $required_params = ['email_id', 'line_number', 'batch_id', 'corrected_total_amount'];
    foreach ($required_params as $param) {
        if (!isset($input[$param])) {
            throw new Exception("Missing required parameter: " . $param, 400);
        }
    }

    $email_id = filter_var($input['email_id'], FILTER_VALIDATE_INT);
    $line_number = filter_var($input['line_number'], FILTER_VALIDATE_INT);
    $batch_id = filter_var($input['batch_id'], FILTER_VALIDATE_INT);
    $corrected_total_amount = filter_var($input['corrected_total_amount'], FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $reason = trim($input['reason'] ?? '');

    // 验证参数有效性
    if (!$email_id || $email_id <= 0) {
        throw new Exception("Invalid email_id: " . ($input['email_id'] ?? 'null'), 400);
    }
    if (!$line_number || $line_number <= 0) {
        throw new Exception("Invalid line_number: " . ($input['line_number'] ?? 'null'), 400);
    }
    if (!$batch_id || $batch_id <= 0) {
        throw new Exception("Invalid batch_id: " . ($input['batch_id'] ?? 'null'), 400);
    }
    if ($corrected_total_amount === false || $corrected_total_amount < 0) {
        throw new Exception("Invalid corrected_total_amount: " . ($input['corrected_total_amount'] ?? 'null'), 400);
    }

    error_log("Parameters validated successfully");

    // 4. 数据库操作
    $pdo = get_db_connection();
    error_log("Database connection established");

    $pdo->beginTransaction();
    error_log("Transaction started");

    // 5. 获取原始解析数据
    $stmt = $pdo->prepare("
        SELECT pb.bet_data_json, pb.email_id
        FROM parsed_bets pb
        JOIN raw_emails re ON pb.email_id = re.id
        WHERE pb.id = ? AND re.user_id = ? AND re.id = ?
    ");
    $stmt->execute([$batch_id, $user_id, $email_id]);
    $original_bet_json = $stmt->fetchColumn();

    if (!$original_bet_json) {
        throw new Exception("Record not found. Batch ID: $batch_id, User ID: $user_id, Email ID: $email_id", 404);
    }

    error_log("Original bet data retrieved");

    $original_parse = json_decode($original_bet_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to decode original bet data: " . json_last_error_msg(), 500);
    }

    $original_text = $original_parse['raw_text'] ?? '';
    $lottery_type = $original_parse['lottery_type'] ?? '香港六合彩';

    error_log("Original text: " . $original_text);
    error_log("Lottery type: " . $lottery_type);

    // 6. 调用AI重新解析
    error_log("Calling AI for re-parsing...");
    $ai_result = analyzeSingleBetWithAI($original_text, $lottery_type, [
        'original_parse' => $original_parse,
        'corrected_total_amount' => $corrected_total_amount,
        'reason' => $reason
    ]);

    error_log("AI result: " . print_r($ai_result, true));

    if (!$ai_result['success']) {
        throw new Exception("AI re-parsing failed: " . ($ai_result['message'] ?? 'Unknown error'), 500);
    }

    if (empty($ai_result['data'])) {
        throw new Exception("AI returned empty data", 500);
    }

    $new_parse_data = $ai_result['data'];
    $new_parse_data['line_number'] = $line_number;
    $new_parse_data['raw_text'] = $original_text;

    error_log("New parse data prepared");

    // 7. 重新结算
    error_log("Starting re-settlement...");

    // 检查结算函数是否存在，使用相对路径引入
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

    // 8. 更新数据库
    $new_bet_json = json_encode($new_parse_data);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to encode new bet data: " . json_last_error_msg(), 500);
    }

    $stmt_update = $pdo->prepare("UPDATE parsed_bets SET bet_data_json = ? WHERE id = ?");
    $stmt_update->execute([$new_bet_json, $batch_id]);

    // 9. 提交事务
    $pdo->commit();
    error_log("Transaction committed successfully");

    // 10. 返回成功响应
    $response = [
        'status' => 'success',
        'message' => 'AI已根据您的提示重新解析并结算！',
        'data' => [
            'batch_id' => $batch_id,
            'parse_result' => $new_parse_data,
            'line_number' => intval($line_number)
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