<?php
// File: backend/auth/get_email_details.php (修复结算计算)

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

    // --- 3. 获取所有彩种的最新开奖结果 ---
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

    foreach ($bet_batches_raw as $batch) {
        $batch_data = json_decode($batch['bet_data_json'], true);
        $batch_info = [
            'batch_id' => $batch['id'],
            'data' => $batch_data,
            'ai_model' => $batch['ai_model_used']
        ];

        // --- 4. 为每个批次计算结算（使用实际开奖结果）---
        if (isset($batch_data['bets']) && is_array($batch_data['bets'])) {
            $lottery_type = $batch_data['lottery_type'] ?? '香港六合彩';
            $lottery_result = $latest_results[$lottery_type] ?? null;
            
            $settlement_data = calculateBatchSettlement($batch_data, $lottery_result);
            $batch_info['settlement'] = $settlement_data;

            // --- 5. 将结算结果嵌入邮件内容 ---
            $enhanced_content = embedSettlementInContent(
                $enhanced_content,
                $batch_data,
                $settlement_data,
                $batch['id']
            );
        }

        $bet_batches[] = $batch_info;
    }

    // --- 6. 如果没有任何批次，确保enhanced_content有内容 ---
    if (empty($bet_batches)) {
        $enhanced_content = $clean_content . "\n\n--- 未检测到下注信息 ---\n";
    }

    // --- 7. 返回增强后的邮件内容 ---
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
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: ' . $e->getMessage()]);
}

/**
 * 计算单个批次的结算结果（使用实际开奖结果）
 */
function calculateBatchSettlement(array $batchData, ?array $lotteryResult = null): array {
    $settlement = [
        'total_bet_amount' => 0,
        'winning_details' => [],
        'net_profits' => [],
        'summary' => '',
        'timestamp' => date('Y-m-d H:i:s'),
        'has_lottery_data' => !is_null($lotteryResult)
    ];

    if (!isset($batchData['bets']) || !is_array($batchData['bets'])) {
        $settlement['summary'] = '无下注数据';
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

                // 如果有开奖结果，进行实际结算计算
                if ($lotteryResult && isset($lotteryResult['winning_numbers']) && is_array($lotteryResult['winning_numbers'])) {
                    $winningNumbers = $lotteryResult['winning_numbers'];
                    
                    if ($betType === '特码' || $betType === '号码') {
                        // 特码玩法：只对比特码（最后一个号码）
                        $specialNumber = end($winningNumbers);
                        if (String($target) === String($specialNumber)) {
                            $winningBets[] = [
                                'number' => $target,
                                'amount' => $amount,
                                'odds' => 45,
                                'bet_type' => $betType
                            ];
                        }
                    } elseif ($betType === '平码') {
                        // 平码玩法：对比所有号码
                        if (in_array(String($target), $winningNumbers)) {
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
    if ($lotteryResult) {
        $settlement['summary'] = "总下注 {$totalBet} 元，中奖 {$winCount} 注";
    } else {
        $settlement['summary'] = "总下注 {$totalBet} 元（等待开奖数据）";
    }

    return $settlement;
}

/**
 * 将结算结果嵌入邮件内容
 */
function embedSettlementInContent(string $content, array $batchData, array $settlement, int $batchId): string {
    $rawText = $batchData['raw_text'] ?? '';

    // 如果没有原始文本，在内容末尾添加结算信息
    if (empty($rawText)) {
        $settlementHtml = buildSettlementHtml($settlement, $batchId);
        return $content . "\n\n" . $settlementHtml;
    }

    // 查找原始文本在内容中的位置
    $position = strpos($content, $rawText);

    if ($position === false) {
        // 如果找不到原始文本，在内容末尾添加结算信息
        $settlementHtml = buildSettlementHtml($settlement, $batchId);
        return $content . "\n\n" . $settlementHtml;
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
 * 构建结算HTML - 使用颜色标记
 */
function buildSettlementHtml(array $settlement, int $batchId): string {
    $html = "\n\n" . str_repeat("=", 50) . "\n";
    $html .= "🎯 结算结果 (批次 {$batchId})\n";
    $html .= str_repeat("=", 50) . "\n";

    // 总下注金额 - 蓝色
    $html .= "💰 总投注金额: <span style='color: blue; font-weight: bold;'>{$settlement['total_bet_amount']} 元</span>\n";

    // 中奖详情
    if (!empty($settlement['winning_details'])) {
        $html .= "🎊 中奖详情:\n";
        foreach ($settlement['winning_details'] as $win) {
            $html .= "   - 号码 {$win['number']}: <span style='color: green; font-weight: bold;'>{$win['amount']} 元</span> (赔率 {$win['odds']})\n";
        }
    } else {
        if ($settlement['has_lottery_data']) {
            $html .= "❌ 中奖详情: <span style='color: red; font-weight: bold;'>未中奖</span>\n";
        } else {
            $html .= "⏳ 中奖详情: <span style='color: orange; font-weight: bold;'>等待开奖数据</span>\n";
        }
    }

    // 不同赔率结算 - 使用红色/蓝色标记盈利/亏损
    $html .= "\n📈 不同赔率结算:\n";
    foreach ($settlement['net_profits'] as $odds => $result) {
        $color = $result['is_profit'] ? 'red' : 'blue';
        $emoji = $result['is_profit'] ? '🟢' : '🔴';
        $profitText = $result['is_profit'] ? "盈利" : "亏损";
        $netAmount = abs($result['net_profit']);

        $html .= "{$emoji} 赔率 {$odds}: <span style='color: {$color}; font-weight: bold;'>{$profitText} {$netAmount} 元</span>\n";
    }

    $html .= str_repeat("=", 50) . "\n";

    return $html;
}
?>
