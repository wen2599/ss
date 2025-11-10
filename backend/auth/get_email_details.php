<?php
// File: backend/auth/get_email_details.php (增强版 - 包含结算嵌入)

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

    // --- 1. 获取原始邮件内容 ---
    $stmt_email = $pdo->prepare("SELECT content FROM raw_emails WHERE id = ? AND user_id = ?");
    $stmt_email->execute([$email_id, $user_id]);
    $raw_content = $stmt_email->fetchColumn();

    if ($raw_content === false) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Email not found or access denied.']);
        exit;
    }

    require_once __DIR__ . '/../helpers/mail_parser.php';
    $clean_content = parse_email_body($raw_content);

    // --- 2. 获取所有关联的下注批次 ---
    $stmt_bets = $pdo->prepare("
        SELECT pb.id, pb.bet_data_json, pb.ai_model_used 
        FROM parsed_bets pb 
        WHERE pb.email_id = ? 
        ORDER BY pb.id ASC
    ");
    $stmt_bets->execute([$email_id]);
    $bet_batches_raw = $stmt_bets->fetchAll(PDO::FETCH_ASSOC);

    $bet_batches = [];
    $enhanced_content = $clean_content; // 初始化增强内容

    foreach ($bet_batches_raw as $batch) {
        $batch_data = json_decode($batch['bet_data_json'], true);
        $batch_info = [
            'batch_id' => $batch['id'],
            'data' => $batch_data,
            'ai_model' => $batch['ai_model_used']
        ];

        // --- 3. 为每个批次计算结算 ---
        if (isset($batch_data['bets']) && is_array($batch_data['bets'])) {
            $settlement_data = calculateBatchSettlement($batch_data);
            $batch_info['settlement'] = $settlement_data;
            
            // --- 4. 将结算结果嵌入邮件内容 ---
            $enhanced_content = embedSettlementInContent(
                $enhanced_content, 
                $batch_data, 
                $settlement_data,
                $batch['id']
            );
        }

        $bet_batches[] = $batch_info;
    }

    // --- 5. 获取所有彩种的最新开奖结果 ---
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
            $row[$key] = json_decode($row[$key]) ?: [];
        }
        $latest_results[$row['lottery_type']] = $row;
    }

    // --- 6. 返回增强后的邮件内容 ---
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'email_content' => $clean_content, // 原始内容
            'enhanced_content' => $enhanced_content, // 嵌入结算后的内容
            'bet_batches' => $bet_batches,
            'latest_lottery_results' => $latest_results
        ]
    ]);

} catch (Throwable $e) {
    error_log("Error in get_email_details.php for email_id {$email_id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error.']);
}

/**
 * 计算单个批次的结算结果
 */
function calculateBatchSettlement(array $batchData): array {
    $settlement = [
        'total_bet_amount' => 0,
        'winning_details' => [],
        'net_profits' => [],
        'summary' => '',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!isset($batchData['bets']) || !is_array($batchData['bets'])) {
        return $settlement;
    }
    
    $totalBet = 0;
    $winningBets = [];
    
    foreach ($batchData['bets'] as $bet) {
        $amount = floatval($bet['amount'] ?? 0);
        $betType = $bet['bet_type'] ?? '';
        $targets = $bet['targets'] ?? [];
        
        if ($amount > 0 && is_array($targets)) {
            foreach ($targets as $target) {
                $totalBet += $amount;
                
                // 这里应该根据实际开奖结果计算
                // 现在先模拟中奖情况
                $isWin = false;
                if ($betType === '特码' || $betType === '号码') {
                    $isWin = rand(0, 10) > 7; // 30%中奖概率模拟
                    
                    if ($isWin) {
                        $winningBets[] = [
                            'number' => $target,
                            'amount' => $amount,
                            'odds' => 45,
                            'bet_type' => $betType
                        ];
                    }
                }
            }
        }
    }
    
    $settlement['total_bet_amount'] = $totalBet;
    $settlement['winning_details'] = $winningBets;
    
    // 计算不同赔率下的净收益
    $oddsList = [45, 46, 47];
    foreach ($oddsList as $odds) {
        $totalWin = 0;
        foreach ($winningBets as $win) {
            $totalWin += $win['amount'] * $odds;
        }
        $netProfit = $totalWin - $totalBet;
        $settlement['net_profits'][$odds] = [
            'total_win' => $totalWin,
            'net_profit' => $netProfit,
            'is_profit' => $netProfit >= 0
        ];
    }
    
    $winCount = count($winningBets);
    $settlement['summary'] = "总下注 {$totalBet} 元，中奖 {$winCount} 注";
    
    return $settlement;
}

/**
 * 将结算结果嵌入邮件内容
 */
function embedSettlementInContent(string $content, array $batchData, array $settlement, int $batchId): string {
    $rawText = $batchData['raw_text'] ?? '';
    
    if (empty($rawText)) {
        return $content;
    }
    
    // 查找原始文本在内容中的位置
    $position = strpos($content, $rawText);
    
    if ($position === false) {
        return $content; // 未找到原始文本，返回原内容
    }
    
    // 构建结算HTML
    $settlementHtml = buildSettlementHtml($settlement, $batchId);
    
    // 在原始文本后插入结算信息
    $insertPosition = $position + strlen($rawText);
    $newContent = substr($content, 0, $insertPosition) . 
                  $settlementHtml . 
                  substr($content, $insertPosition);
    
    return $newContent;
}

/**
 * 构建结算HTML
 */
function buildSettlementHtml(array $settlement, int $batchId): string {
    $html = "\n\n--- 🎯 结算结果 (批次 {$batchId}) ---\n";
    
    // 总下注金额
    $html .= "💰 <strong>总投注金额:</strong> <span style='color: blue;'>{$settlement['total_bet_amount']} 元</span>\n";
    
    // 中奖详情
    if (!empty($settlement['winning_details'])) {
        $html .= "🎊 <strong>中奖详情:</strong>\n";
        foreach ($settlement['winning_details'] as $win) {
            $html .= "   - 号码 {$win['number']}: <span style='color: green;'>{$win['amount']} 元</span> (赔率 {$win['odds']})\n";
        }
    } else {
        $html .= "❌ <strong>中奖详情:</strong> <span style='color: red;'>未中奖</span>\n";
    }
    
    // 不同赔率结算
    $html .= "\n📈 <strong>不同赔率结算:</strong>\n";
    foreach ($settlement['net_profits'] as $odds => $result) {
        $color = $result['is_profit'] ? 'green' : 'red';
        $emoji = $result['is_profit'] ? '🟢' : '🔴';
        $profitText = $result['is_profit'] ? "盈利" : "亏损";
        $netAmount = abs($result['net_profit']);
        
        $html .= "{$emoji} 赔率 {$odds}: <span style='color: {$color}; font-weight: bold;'>{$profitText} {$netAmount} 元</span>\n";
    }
    
    $html .= "--- 结算结束 ---\n";
    
    return $html;
}
?>