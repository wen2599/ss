<?php

declare(strict_types=1);

// backend/api/save_settlement.php

require_once __DIR__ . '/../bootstrap.php';

header("Content-Type: application/json");

// 1. 验证请求方法和JWT
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => '仅允许POST方法']);
    exit;
}

$user_id = verify_jwt_token();
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '认证失败，请重新登录。']);
    exit;
}

// 2. 获取并验证输入数据
$data = json_decode(file_get_contents('php://input'), true);
$email_id = filter_var($data['emailId'] ?? null, FILTER_VALIDATE_INT);
$settlement_data_raw = $data['settlementData'] ?? null;

if (!$email_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '请求参数错误：缺少邮件ID。']);
    exit;
}
if (!is_array($settlement_data_raw) || empty($settlement_data_raw['bets'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '结算单数据无效或为空。']);
    exit;
}

// 从结算单数据中提取并验证关键信息
$draw_period = trim($settlement_data_raw['draw_period'] ?? '');
$customer_name = trim($settlement_data_raw['customer_name'] ?? '');
$total_amount = filter_var($settlement_data_raw['total_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
$bets_json = json_encode($settlement_data_raw['bets'], JSON_UNESCAPED_UNICODE);

if (empty($draw_period)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '期号不能为空。']);
    exit;
}
if (empty($customer_name)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '客户名不能为空。']);
    exit;
}
if ($total_amount === false || $total_amount < 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '总金额无效。']);
    exit;
}
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '结算单中的投注数据格式错误，请检查。']);
    exit;
}

global $db_connection;

// 3. 开启数据库事务
$db_connection->begin_transaction();

try {
    // 任务 A: 插入或更新 settlements 表
    $stmt_settlement = $db_connection->prepare(
        "INSERT INTO settlements (email_id, user_id, draw_period, customer_name, total_amount, settlement_data, status) " .
        "VALUES (?, ?, ?, ?, ?, ?, 'pending_settlement') " .
        "ON DUPLICATE KEY UPDATE " .
        "draw_period = VALUES(draw_period), " .
        "customer_name = VALUES(customer_name), " .
        "total_amount = VALUES(total_amount), " .
        "settlement_data = VALUES(settlement_data), " .
        "status = VALUES(status), " .
        "updated_at = CURRENT_TIMESTAMP()"
    );
    // total_winnings 默认为 NULL，status 默认为 pending_settlement
    $stmt_settlement->bind_param("iissds", $email_id, $user_id, $draw_period, $customer_name, $total_amount, $bets_json);
    
    if (!$stmt_settlement->execute()) {
        throw new Exception("保存结算单失败: " . $stmt_settlement->error);
    }
    $stmt_settlement->close();

    // 任务 B: 更新 emails 表的状态
    $stmt_email = $db_connection->prepare("UPDATE emails SET is_processed = 1 WHERE id = ? AND user_id = ?");
    $stmt_email->bind_param("ii", $email_id, $user_id);

    if (!$stmt_email->execute()) {
        throw new Exception("更新邮件处理状态失败: " . $stmt_email->error);
    }
    $stmt_email->close();

    // 4. 提交事务
    $db_connection->commit();

    // 5. 成功响应
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => '结算单已成功保存!']);

} catch (Exception $e) {
    // 如果任何一步失败，回滚所有操作
    $db_connection->rollback();
    error_log("Save Settlement Error for email_id {$email_id}, user_id {$user_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '保存结算单时发生内部错误。请联系管理员。' . (getenv('APP_DEBUG') ? ' 错误详情: ' . $e->getMessage() : '')]);
}
