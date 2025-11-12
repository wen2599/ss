<?php
// File: backend/auth/split_email_lines.php (优化版)

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

    // 智能拆分下注单 - 使用优化算法
    $bet_lines = splitBetLinesIntelligently($clean_content);

    // 获取已解析的批次
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
 * 智能拆分下注单行 - 优化版
 */
function splitBetLinesIntelligently(string $content): array {
    $lines = explode("\n", $content);
    $bet_lines = [];
    
    // 定义下注关键词模式
    $bet_patterns = [
        // 澳门模式
        '/澳门[，,].*?\d+.*?(元|块|#)/u',
        // 香港模式  
        '/香港[：:].*?\d+.*?(元|块|#)/u',
        // 号码模式
        '/\b\d{2}(?:[.,]\d{2})*.*?(各|×|x)\s*\d+\s*(元|块|#)/u',
        // 生肖模式
        '/[鼠牛虎兔龙蛇马羊猴鸡狗猪].*?\d+\s*(元|块|闷)/u',
        // 金额模式
        '/各\s*\d+\s*(元|块|#|闷)/u',
        // 六肖模式
        '/[鼠牛虎兔龙蛇马羊猴鸡狗猪]{2,}.*?\d+\s*(元|块|闷)/u'
    ];
    
    $current_bet = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // 跳过聊天记录头部和非下注内容
        if (preg_match('/^(胜利|微信|聊天记录|—————|\d{4}-\d{2}-\d{2}|老都)/u', $line)) {
            // 如果当前有累积的下注内容，先保存
            if (!empty($current_bet)) {
                $bet_lines[] = $current_bet;
                $current_bet = '';
            }
            continue;
        }
        
        // 检查是否包含下注特征
        $is_bet_line = false;
        foreach ($bet_patterns as $pattern) {
            if (preg_match($pattern, $line)) {
                $is_bet_line = true;
                break;
            }
        }
        
        if ($is_bet_line) {
            // 如果当前有累积内容，先保存
            if (!empty($current_bet)) {
                $bet_lines[] = $current_bet;
            }
            $current_bet = $line;
        } else if (!empty($current_bet)) {
            // 如果不是下注行但当前有累积，可能是下注的延续
            $current_bet .= ' ' . $line;
        }
    }
    
    // 添加最后一条
    if (!empty($current_bet)) {
        $bet_lines[] = $current_bet;
    }
    
    return array_filter($bet_lines, function($line) {
        $line = trim($line);
        if (empty($line)) return false;
        
        // 最终过滤：必须包含数字和金额单位
        return preg_match('/\d.*?(元|块|#|闷)|(各|×|x)\s*\d/u', $line);
    });
}

/**
 * 旧版拆分函数（保留兼容性）
 */
function splitBetLines(string $content): array {
    return splitBetLinesIntelligently($content);
}
?>
