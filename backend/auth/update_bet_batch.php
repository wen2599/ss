<?php
// File: backend/auth/update_bet_batch.php (with AI Learning)

// Include necessary files
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_operations.php';
require_once __DIR__ . '/../ai_helper.php'; // Include for trainAIWithCorrection

/**
 * 触发AI学习修正
 */
function triggerAILearning($batch_id, $original_bet_data, $corrected_data) {
    try {
        $pdo = get_db_connection();
        // 获取原始邮件内容
        $stmt = $pdo->prepare("
            SELECT re.content 
            FROM parsed_bets pb
            JOIN raw_emails re ON pb.email_id = re.id
            WHERE pb.id = ?
        ");
        $stmt->execute([$batch_id]);
        $original_content = $stmt->fetchColumn();
        
        if ($original_content) {
            // 构建学习数据
            $learning_data = [
                'original_text' => $original_content,
                'original_parse' => $original_bet_data,
                'corrected_parse' => $corrected_data,
                'learning_timestamp' => date('Y-m-d H:i:s')
            ];
            
            // 实际调用 AI 学习函数
            trainAIWithCorrection($learning_data);
            error_log("AI Learning Triggered for batch_id: " . $batch_id);

        } else {
            error_log("Could not find original email content for batch_id: " . $batch_id);
        }
        
    } catch (Exception $e) {
        error_log("Error in AI learning trigger for batch_id {$batch_id}: " . $e->getMessage());
    }
}


// --- Main script execution ---

// 1. 身份验证
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// 2. 获取并验证输入
$input = json_decode(file_get_contents('php://input'), true);
$batch_id = $input['batch_id'] ?? null;
$updated_data = $input['data'] ?? null;

if (empty($batch_id) || !is_numeric($batch_id) || $updated_data === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input: batch_id and data are required.']);
    exit;
}

$updated_data_json = json_encode($updated_data);
if ($updated_data_json === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data provided.']);
    exit;
}

$pdo = null;
try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 3. 安全性检查 & 获取原始数据
    $stmt_check = $pdo->prepare("
        SELECT pb.id, pb.bet_data_json 
        FROM parsed_bets pb
        JOIN raw_emails re ON pb.email_id = re.id
        WHERE pb.id = ? AND re.user_id = ?
    ");
    $stmt_check->execute([$batch_id, $_SESSION['user_id']]);
    $original_record = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($original_record === false) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied or record not found.']);
        $pdo->rollBack();
        exit;
    }
    
    $original_bet_data = json_decode($original_record['bet_data_json'], true);

    // 4. 执行更新
    $stmt_update = $pdo->prepare("UPDATE parsed_bets SET bet_data_json = ? WHERE id = ?");
    $stmt_update->execute([$updated_data_json, $batch_id]);

    // 5. 触发AI学习 (如果需要)
    if (isset($updated_data['correction'])) {
        // 记录修正到数据库
        try {
            $stmt_correction = $pdo->prepare("
                INSERT INTO ai_corrections 
                (batch_id, original_amount, corrected_amount, correction_reason, corrected_at, user_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_correction->execute([
                $batch_id,
                $updated_data['correction']['original_amount'],
                $updated_data['correction']['corrected_amount'],
                $updated_data['correction']['correction_reason'],
                $updated_data['correction']['corrected_at'],
                $_SESSION['user_id']
            ]);
        } catch (Exception $e) {
            error_log("Failed to save AI correction to DB: " . $e->getMessage());
            // This might not be a fatal error, so we log it but don't stop the process
        }
        
        // 触发AI学习
        triggerAILearning($batch_id, $original_bet_data, $updated_data);
    }
    
    // 6. 提交事务
    $pdo->commit();

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Bet batch updated successfully.']);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error updating bet batch {$batch_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred during the update process.']);
}

?>