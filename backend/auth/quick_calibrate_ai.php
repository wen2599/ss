<?php
// File: backend/auth/quick_calibrate_ai.php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../debug.log');

// 辅助函数：安全返回 JSON，防止 UTF-8 错误导致空响应
function safe_json_response($data, $code = 200) {
    http_response_code($code);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    if ($json === false) {
        echo '{"status":"error","message":"JSON Encoding Failed: ' . json_last_error_msg() . '"}';
    } else {
        echo $json;
    }
    exit;
}

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../db_operations.php';
    require_once __DIR__ . '/../ai_helper.php';

    if (!isset($_SESSION['user_id'])) {
        safe_json_response(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }
    $user_id = $_SESSION['user_id'];

    $input_json = file_get_contents('php://input');
    
    // 调试：记录收到的 Keys
    $input = json_decode($input_json, true);
    if ($input) {
        error_log("QuickCalibrate Recv Keys: " . implode(',', array_keys($input)));
    } else {
        error_log("QuickCalibrate Recv Invalid/Empty JSON. Raw Len: " . strlen($input_json));
        throw new Exception("接收到的数据为空或格式错误", 400);
    }

    $email_id = intval($input['email_id'] ?? 0);
    $line_number = intval($input['line_number'] ?? 0);
    $batch_id = intval($input['batch_id'] ?? 0);
    $corrected_total_amount = floatval($input['corrected_total_amount'] ?? 0);
    $reason = trim($input['reason'] ?? '');

    if ($email_id <= 0) throw new Exception("缺少参数: email_id", 400);
    if ($batch_id <= 0) throw new Exception("缺少参数: batch_id", 400);

    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT pb.bet_data_json, pb.email_id FROM parsed_bets pb JOIN raw_emails re ON pb.email_id = re.id WHERE pb.id = ? AND re.user_id = ?");
    $stmt->execute([$batch_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) throw new Exception("找不到记录", 404);

    $original_parse = json_decode($result['bet_data_json'], true);
    $original_text = $original_parse['raw_text'] ?? '';
    $lottery_type = $original_parse['lottery_type'] ?? '香港六合彩';

    // 调用 AI
    $ai_result = analyzeSingleBetWithAI($original_text, $lottery_type, [
        'original_parse' => $original_parse,
        'corrected_total_amount' => $corrected_total_amount,
        'reason' => $reason
    ]);

    if (!$ai_result['success']) {
        throw new Exception("AI解析失败: " . ($ai_result['message'] ?? 'Unknown'), 500);
    }

    $new_parse_data = $ai_result['data'];
    $new_parse_data['line_number'] = $line_number;
    $new_parse_data['raw_text'] = $original_text;

    // 重新结算
    if (!function_exists('calculateBatchSettlement')) require_once __DIR__ . '/get_email_details.php';
    
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
                $row[$key] = json_decode($row[$key], true) ?: [];
            }
            $latest_results[$type] = $row;
        }
    }

    $settlement_data = calculateBatchSettlement($new_parse_data, $latest_results[$new_parse_data['lottery_type']] ?? null, $userOddsTemplate);
    $new_parse_data['settlement'] = $settlement_data;

    $stmt_update = $pdo->prepare("UPDATE parsed_bets SET bet_data_json = ? WHERE id = ?");
    $stmt_update->execute([json_encode($new_parse_data, JSON_UNESCAPED_UNICODE), $batch_id]);

    $pdo->commit();

    safe_json_response([
        'status' => 'success',
        'message' => '校准成功',
        'data' => [
            'batch_id' => $batch_id,
            'parse_result' => $new_parse_data,
            'line_number' => $line_number
        ]
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("QuickCalibrate Error: " . $e->getMessage());
    safe_json_response(['status' => 'error', 'message' => $e->getMessage()], 500);
}
?>