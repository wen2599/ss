<?php
// File: backend/auth/smart_parse_email.php
require_once __DIR__ . '/../db_operations.php';

// 1. 身份验证
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 2. 获取输入参数
$input = json_decode(file_get_contents('php://input'), true);
$email_id = $input['email_id'] ?? null;
$lottery_types = $input['lottery_types'] ?? [];

if (empty($email_id) || empty($lottery_types)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email ID and lottery types are required.']);
    exit;
}

// 3. 验证邮件属于当前用户
try {
    $pdo = get_db_connection();
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT id, content FROM raw_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$email_id, $user_id]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
        exit;
    }

    // 4. 获取用户赔率模板
    $stmt_odds = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
    $stmt_odds->execute([$user_id]);
    $userOddsTemplate = $stmt_odds->fetch(PDO::FETCH_ASSOC);

    if (!$userOddsTemplate || empty(array_filter($userOddsTemplate, function($value) {
        return $value !== null;
    }))) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => '请先设置赔率模板']);
        exit;
    }

    // 5. 获取选择的彩票类型的开奖结果
    require_once __DIR__ . '/../helpers/mail_parser.php';
    $clean_content = parse_email_body($email['content']);
    
    // 获取最新开奖结果
    $sql_latest_results = "
        SELECT r1.*
        FROM lottery_results r1
        JOIN (
            SELECT lottery_type, MAX(id) AS max_id
            FROM lottery_results
            GROUP BY lottery_type
        ) r2 ON r1.lottery_type = r2.lottery_type AND r1.id = r2.max_id
        WHERE r1.lottery_type IN (" . implode(',', array_fill(0, count($lottery_types), '?')) . ")
    ";
    $stmt_latest = $pdo->prepare($sql_latest_results);
    $stmt_latest->execute($lottery_types);
    $latest_results_raw = $stmt_latest->fetchAll(PDO::FETCH_ASSOC);

    $latest_results = [];
    foreach ($latest_results_raw as $row) {
        foreach(['winning_numbers', 'zodiac_signs', 'colors'] as $key) {
            $decoded = json_decode($row[$key], true);
            $row[$key] = $decoded ?: [];
        }
        $latest_results[$row['lottery_type']] = $row;
    }

    // 6. 先尝试模板解析
    require_once __DIR__ . '/../helpers/manual_parser.php';
    $manual_data = parseBetManually($clean_content);
    $parse_method = 'template';
    
    // 7. 如果模板解析没有结果，使用AI解析
    if (empty($manual_data['bets'])) {
        require_once __DIR__ . '/../ai_helper.php';
        $ai_result = analyzeBetSlipWithAI($email['content']);
        $parse_method = 'ai';
        
        if ($ai_result['success'] && isset($ai_result['data'])) {
            $manual_data = $ai_result['data'];
            // 添加原始文本到AI解析结果中
            if (isset($manual_data['bets'])) {
                foreach ($manual_data['bets'] as &$bet) {
                    $bet['raw_text'] = $bet['raw_text'] ?? 'AI解析';
                }
            }
        }
    }

    // 8. 计算结算结果
    require_once __DIR__ . '/get_email_details.php'; // 为了使用结算函数
    $settlement_data = calculateManualSettlement($manual_data, $latest_results, $userOddsTemplate);

    // 9. 删除旧的解析记录并保存新的
    $stmt_delete = $pdo->prepare("DELETE FROM parsed_bets WHERE email_id = ?");
    $stmt_delete->execute([$email_id]);

    $model_used = $parse_method === 'ai' ? 'cloudflare_ai' : 'manual_template';
    $bet_data_json = json_encode($manual_data);

    $stmt_insert = $pdo->prepare("INSERT INTO parsed_bets (email_id, bet_data_json, ai_model_used) VALUES (?, ?, ?)");
    $stmt_insert->execute([$email_id, $bet_data_json, $model_used]);

    // 10. 更新邮件状态
    $stmt_update = $pdo->prepare("UPDATE raw_emails SET status = 'processed' WHERE id = ?");
    $stmt_update->execute([$email_id]);

    // 11. 返回结果
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => '解析完成',
        'parse_method' => $parse_method,
        'batch_id' => $pdo->lastInsertId(),
        'settlement' => $settlement_data,
        'bet_data' => $manual_data
    ]);

} catch (Throwable $e) {
    error_log("Error in smart_parse_email for email {$email_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '解析失败: ' . $e->getMessage()]);
}
?>