<?php
// File: backend/auth/update_bet_batch.php (Complete Version)

// 1. 身份验证
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 2. 获取并验证输入
$input = json_decode(file_get_contents('php://input'), true);
$batch_id = $input['batch_id'] ?? null;
$updated_data = $input['data'] ?? null; // The new JSON object/array for bet_data_json

if (empty($batch_id) || !is_numeric($batch_id) || $updated_data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input: batch_id and data are required.']);
    exit;
}

// 将接收到的新数据重新编码为 JSON 字符串
$updated_data_json = json_encode($updated_data);
if ($updated_data_json === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data provided.']);
    exit;
}

try {
    $pdo = get_db_connection();

    // 3. 安全性检查 (非常重要!)
    // 在更新前，必须确认这条 bet batch 记录确实属于当前登录的用户。
    // 我们通过 parsed_bets -> raw_emails -> users 的连接来实现。
    $stmt_check = $pdo->prepare("
        SELECT pb.id FROM parsed_bets pb
        JOIN raw_emails re ON pb.email_id = re.id
        WHERE pb.id = ? AND re.user_id = ?
    ");
    $stmt_check->execute([$batch_id, $_SESSION['user_id']]);
    
    if ($stmt_check->fetchColumn() === false) {
        // 如果查询没有返回任何结果，说明这条记录不属于当前用户
        http_response_code(403); // Forbidden
        echo json_encode(['status' => 'error', 'message' => 'Access denied: You do not own this bet batch.']);
        exit;
    }

    // 4. 执行更新
    $stmt_update = $pdo->prepare("UPDATE parsed_bets SET bet_data_json = ? WHERE id = ?");
    $stmt_update->execute([$updated_data_json, $batch_id]);

    if ($stmt_update->rowCount() > 0) {
        // 更新成功
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Bet batch updated successfully.']);
    } else {
        // rowCount() 为 0 可能意味着记录存在，但提交的数据与库中数据完全相同，未发生实际更新。
        // 这也应该被视为一种成功。
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'No changes detected, but acknowledged.']);
    }

} catch (PDOException $e) {
    error_log("Error updating bet batch {$batch_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'A database error occurred during the update.']);
}
?>