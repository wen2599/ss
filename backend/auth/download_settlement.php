<?php
// File: backend/auth/download_settlement.php

// 1. 身份验证
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$email_id = $_GET['id'] ?? null;

if (empty($email_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email ID is required.']);
    exit;
}

try {
    $pdo = get_db_connection();

    // 验证邮件属于当前用户
    $stmt_email = $pdo->prepare("SELECT content FROM raw_emails WHERE id = ? AND user_id = ?");
    $stmt_email->execute([$email_id, $user_id]);
    $raw_content = $stmt_email->fetchColumn();

    if ($raw_content === false) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Email not found or access denied.']);
        exit;
    }

    // 获取增强后的内容（包含结算信息）
    require_once __DIR__ . '/../helpers/mail_parser.php';
    $clean_content = parse_email_body($raw_content);

    // 获取所有关联的下注批次和结算信息
    $stmt_bets = $pdo->prepare("
        SELECT pb.id, pb.bet_data_json, pb.ai_model_used
        FROM parsed_bets pb
        WHERE pb.email_id = ?
        ORDER BY pb.id ASC
    ");
    $stmt_bets->execute([$email_id]);
    $bet_batches_raw = $stmt_bets->fetchAll(PDO::FETCH_ASSOC);

    $enhanced_content = $clean_content;

    // 如果没有批次，使用手动解析
    if (empty($bet_batches_raw)) {
        require_once __DIR__ . '/../helpers/manual_parser.php';
        $manual_data = parseBetManually($clean_content);
        if (!empty($manual_data['bets'])) {
            $enhanced_content = enhanceEmailContent($clean_content, $manual_data);
        }
    } else {
        // 处理每个批次，将结算信息嵌入内容
        foreach ($bet_batches_raw as $batch) {
            $batch_data = json_decode($batch['bet_data_json'], true);
            
            // 为每个批次添加结算信息到内容中
            $settlement_info = "\n\n" . str_repeat("=", 50) . "\n";
            $settlement_info .= "🎯 结算结果 (批次 {$batch['id']})\n";
            $settlement_info .= str_repeat("=", 50) . "\n";
            
            $total_bet = 0;
            if (isset($batch_data['bets']) && is_array($batch_data['bets'])) {
                foreach ($batch_data['bets'] as $bet) {
                    $amount = floatval($bet['amount'] ?? 0);
                    $targets = $bet['targets'] ?? [];
                    if ($amount > 0 && is_array($targets)) {
                        $total_bet += $amount * count($targets);
                    }
                }
            }
            
            $settlement_info .= "💰 总投注金额: {$total_bet} 元\n";
            $settlement_info .= "📊 AI模型: {$batch['ai_model_used']}\n";
            $settlement_info .= str_repeat("=", 50) . "\n";
            
            $enhanced_content .= $settlement_info;
        }
    }

    // 生成文件名（日期时间格式）
    $filename = date('Ymd_His') . '_settlement.txt';

    // 设置响应头，直接输出TXT文件
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($enhanced_content));
    
    echo $enhanced_content;
    exit;

} catch (Throwable $e) {
    error_log("Error generating settlement file for email_id {$email_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: ' . $e->getMessage()]);
}
?>
