<?php
// File: backend/auth/split_email_lines.php (修复版)

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$email_id = $_GET['id'] ?? null;

if (empty($email_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email ID is required.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $user_id = $_SESSION['user_id'];

    // 获取邮件内容
    $stmt = $pdo->prepare("SELECT content FROM raw_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([$email_id, $user_id]);
    $raw_content = $stmt->fetchColumn();

    if ($raw_content === false) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Email not found.']);
        exit;
    }

    require_once __DIR__ . '/../helpers/mail_parser.php';
    $clean_content = parse_email_body($raw_content);

    // 智能拆分下注单
    $bet_lines = splitBetLines($clean_content);

    // 获取已解析的批次 - 修复查询
    $stmt_bets = $pdo->prepare("
        SELECT id, bet_data_json, line_number 
        FROM parsed_bets 
        WHERE email_id = ? 
        ORDER BY line_number ASC
    ");
    $stmt_bets->execute([$email_id]);
    $existing_batches = $stmt_bets->fetchAll(PDO::FETCH_ASSOC);

    // 组织响应数据
    $lines_data = [];
    foreach ($bet_lines as $index => $line) {
        $line_number = $index + 1;
        $existing_batch = null;
        
        // 查找是否已解析
        foreach ($existing_batches as $batch) {
            $batch_data = json_decode($batch['bet_data_json'], true);
            if ($batch['line_number'] == $line_number) {
                $existing_batch = [
                    'batch_id' => $batch['id'],
                    'data' => $batch_data,
                    'line_number' => $line_number
                ];
                break;
            }
        }

        $lines_data[] = [
            'line_number' => $line_number,
            'text' => trim($line),
            'is_parsed' => !is_null($existing_batch),
            'batch_data' => $existing_batch
        ];
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'email_content' => $clean_content,
            'lines' => $lines_data,
            'total_lines' => count($bet_lines)
        ]
    ]);

} catch (Throwable $e) {
    error_log("Error splitting email lines: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '拆分失败: ' . $e->getMessage()]);
}

/**
 * 智能拆分下注单行
 */
function splitBetLines(string $content): array {
    $lines = explode("\n", $content);
    $bet_lines = [];
    $current_line = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // 检测是否为新下注单的开始
        $is_bet_start = preg_match('/(澳门|香港|鼠|牛|虎|兔|龙|蛇|马|羊|猴|鸡|狗|猪|平特|串码|大小|单双|各\d+(元|块|#))/u', $line);
        
        if ($is_bet_start && !empty($current_line)) {
            $bet_lines[] = $current_line;
            $current_line = $line;
        } else {
            if (!empty($current_line)) {
                $current_line .= ' ' . $line;
            } else {
                $current_line = $line;
            }
        }
    }

    // 添加最后一行
    if (!empty($current_line)) {
        $bet_lines[] = $current_line;
    }

    return array_filter($bet_lines, function($line) {
        return !empty(trim($line));
    });
}
?>