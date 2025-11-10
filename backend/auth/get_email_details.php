<?php
// File: backend/auth/get_email_details.php (完整版 - 直接使用彩票号码结算)

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
    $enhanced_content = $clean_content;

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

    // --- 4. 处理下注批次 ---
    foreach ($bet_batches_raw as $batch) {
        $batch_data = json_decode($batch['bet_data_json'], true);
        $batch_info = [
            'batch_id' => $batch['id'],
            'data' => $batch_data,
            'ai_model' => $batch['ai_model_used']
        ];

        // --- 5. 为每个批次计算结算（使用实际开奖结果）---
        if (isset($batch_data['bets']) && is_array($batch_data['bets'])) {
            $lottery_type = $batch_data['lottery_type'] ?? '香港六合彩';
            $lottery_result = $latest_results[$lottery_type] ?? null;
            
            $settlement_data = calculateBatchSettlement($batch_data, $lottery_result);
            $batch_info['settlement'] = $settlement_data;

            // --- 6. 将结算结果嵌入邮件内容 ---
            $enhanced_content = embedSettlementInContent(
                $enhanced_content,
                $batch_data,
                $settlement_data,
                $batch['id']
            );
        }

        $bet_batches[] = $batch_info;
    }

    // --- 7. 如果没有任何批次，使用手动解析 ---
    if (empty($bet_batches)) {
        $manual_data = parseBetManually($clean_content);
        if (!empty($manual_data['bets'])) {
            // 为手动解析的数据计算结算
            $settlement_data = calculateManualSettlement($manual_data, $latest_results);
            
            $batch_info = [
                'batch_id' => 0,
                'data' => $manual_data,
                'ai_model' => 'manual_parser',
                'settlement' => $settlement_data
            ];
            
            $bet_batches[] = $batch_info;
            $enhanced_content = embedManualSettlement($clean_content, $manual_data, $settlement_data);
        } else {
            $enhanced_content = $clean_content . "\n\n--- 未检测到下注信息 ---\n";
        }
    }

    // --- 8. 返回增强后的邮件内容 ---
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'email_content' => $clean_content,
            'enhanced_content' => $enhanced_content,
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
                        if (strval($target) === strval($specialNumber)) {
                            $winningBets[] = [
                                'number' => $target,
                                'amount' => $amount,
                                'odds' => 45,
                                'bet_type' => $betType
                            ];
                        }
                    } elseif ($betType === '平码') {
                        // 平码玩法：对比所有号码
                        if (in_array(strval($target), array_map('strval', $winningNumbers))) {
                            $winningBets[] = [
                                'number' => $target,
                                'amount' => $amount,
                                'odds' => 45,
                                'bet_type' => $betType
                            ];
                        }
                    } elseif ($betType === '生肖') {
                        // 生肖玩法：根据号码对应的生肖来判断
                        $targetZodiac = getZodiacByNumber($target);
                        if ($targetZodiac && in_array($targetZodiac, $lotteryResult['zodiac_signs'] ?? [])) {
                            $winningBets[] = [
                                'number' => $target,
                                'amount' => $amount,
                                'odds' => 45,
                                'bet_type' => $betType,
                                'zodiac' => $targetZodiac
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
 * 手动解析下注信息
 */
function parseBetManually(string $text): array {
    $bets = [];
    $totalAmount = 0;
    
    // 解析澳门号码下注
    if (preg_match('/澳门(.+?)各(\d+)#/', $text, $matches)) {
        $numbersText = $matches[1];
        $amount = intval($matches[2]);
        
        // 提取号码
        preg_match_all('/\d+/', $numbersText, $numberMatches);
        $numbers = $numberMatches[0];
        
        foreach ($numbers as $number) {
            $bets[] = [
                'bet_type' => '号码',
                'targets' => [$number],
                'amount' => $amount,
                'raw_text' => "澳门{$number}各{$amount}#"
            ];
            $totalAmount += $amount;
        }
    }
    
    // 解析生肖下注
    if (preg_match('/([鼠牛虎兔龙蛇马羊猴鸡狗猪]+)[，,]\s*([鼠牛虎兔龙蛇马羊猴鸡狗猪]+)数各(\d+)元/', $text, $matches)) {
        $zodiac1 = $matches[1];
        $zodiac2 = $matches[2];
        $amount = intval($matches[3]);
        
        $bets[] = [
            'bet_type' => '生肖',
            'targets' => [$zodiac1, $zodiac2],
            'amount' => $amount,
            'raw_text' => "{$zodiac1}，{$zodiac2}数各{$amount}元"
        ];
        $totalAmount += $amount * 2;
    }
    
    // 解析香港号码下注
    if (preg_match('/香港：(.+?)各(\d+)块/', $text, $matches)) {
        $numbersText = $matches[1];
        $amount = intval($matches[2]);
        
        // 提取号码（用点号分隔）
        $numbers = explode('.', $numbersText);
        $numbers = array_filter($numbers, function($num) {
            return !empty(trim($num));
        });
        
        foreach ($numbers as $number) {
            $bets[] = [
                'bet_type' => '号码',
                'targets' => [trim($number)],
                'amount' => $amount,
                'raw_text' => "香港号码{$number}各{$amount}块"
            ];
            $totalAmount += $amount;
        }
    }
    
    return [
        'lottery_type' => '混合',
        'issue_number' => '',
        'bets' => $bets,
        'total_amount' => $totalAmount
    ];
}

/**
 * 计算手动解析的结算
 */
function calculateManualSettlement(array $manualData, array $latestResults): array {
    $settlement = [
        'total_bet_amount' => $manualData['total_amount'],
        'winning_details' => [],
        'net_profits' => [],
        'summary' => '',
        'timestamp' => date('Y-m-d H:i:s'),
        'has_lottery_data' => false
    ];

    $totalWin = 0;
    $winningBets = [];

    // 使用所有彩票类型的结果进行结算
    foreach ($manualData['bets'] as $bet) {
        $amount = $bet['amount'];
        $betType = $bet['bet_type'];
        $targets = $bet['targets'];

        foreach ($targets as $target) {
            // 检查所有彩票类型是否中奖
            foreach ($latestResults as $lotteryType => $result) {
                if (!isset($result['winning_numbers']) || !is_array($result['winning_numbers'])) {
                    continue;
                }

                $winningNumbers = $result['winning_numbers'];
                
                if ($betType === '号码') {
                    // 特码玩法：对比特码（最后一个号码）
                    $specialNumber = end($winningNumbers);
                    if (strval($target) === strval($specialNumber)) {
                        $winningBets[] = [
                            'number' => $target,
                            'amount' => $amount,
                            'odds' => 45,
                            'bet_type' => $betType,
                            'lottery_type' => $lotteryType
                        ];
                        $totalWin += $amount * 45;
                        break; // 中奖后跳出彩票类型循环
                    }
                } elseif ($betType === '生肖') {
                    // 生肖玩法：根据号码对应的生肖来判断
                    $targetZodiac = getZodiacByNumber($target);
                    if ($targetZodiac && in_array($targetZodiac, $result['zodiac_signs'] ?? [])) {
                        $winningBets[] = [
                            'number' => $target,
                            'amount' => $amount,
                            'odds' => 45,
                            'bet_type' => $betType,
                            'zodiac' => $targetZodiac,
                            'lottery_type' => $lotteryType
                        ];
                        $totalWin += $amount * 45;
                        break; // 中奖后跳出彩票类型循环
                    }
                }
            }
        }
    }

    $settlement['winning_details'] = $winningBets;
    $settlement['has_lottery_data'] = !empty($latestResults);

    // 计算不同赔率下的净收益
    $oddsList = [45, 46, 47];
    foreach ($oddsList as $odds) {
        $totalWinForOdds = 0;
        foreach ($winningBets as $win) {
            $totalWinForOdds += $win['amount'] * $odds;
        }
        $netProfit = $totalWinForOdds - $manualData['total_amount'];
        $settlement['net_profits'][$odds] = [
            'total_win' => $totalWinForOdds,
            'net_profit' => $netProfit,
            'is_profit' => $netProfit >= 0
        ];
    }

    $winCount = count($winningBets);
    if (!empty($latestResults)) {
        $settlement['summary'] = "总下注 {$manualData['total_amount']} 元，中奖 {$winCount} 注";
    } else {
        $settlement['summary'] = "总下注 {$manualData['total_amount']} 元（等待开奖数据）";
    }

    return $settlement;
}

/**
 * 根据号码获取生肖
 */
function getZodiacByNumber($number): ?string {
    $zodiacMap = [
        '01' => '鼠', '13' => '鼠', '25' => '鼠', '37' => '鼠', '49' => '鼠',
        '02' => '牛', '14' => '牛', '26' => '牛', '38' => '牛',
        '03' => '虎', '15' => '虎', '27' => '虎', '39' => '虎',
        '04' => '兔', '16' => '兔', '28' => '兔', '40' => '兔',
        '05' => '龙', '17' => '龙', '29' => '龙', '41' => '龙',
        '06' => '蛇', '18' => '蛇', '30' => '蛇', '42' => '蛇',
        '07' => '马', '19' => '马', '31' => '马', '43' => '马',
        '08' => '羊', '20' => '羊', '32' => '羊', '44' => '羊',
        '09' => '猴', '21' => '猴', '33' => '猴', '45' => '猴',
        '10' => '鸡', '22' => '鸡', '34' => '鸡', '46' => '鸡',
        '11' => '狗', '23' => '狗', '35' => '狗', '47' => '狗',
        '12' => '猪', '24' => '猪', '36' => '猪', '48' => '猪'
    ];
    
    $numberPadded = str_pad(strval($number), 2, '0', STR_PAD_LEFT);
    return $zodiacMap[$numberPadded] ?? null;
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
 * 嵌入手动解析的结算结果
 */
function embedManualSettlement(string $content, array $manualData, array $settlement): string {
    $settlementHtml = buildSettlementHtml($settlement, 0);
    return $content . "\n\n" . $settlementHtml;
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
            $lotteryTypeInfo = isset($win['lottery_type']) ? " ({$win['lottery_type']})" : "";
            $zodiacInfo = isset($win['zodiac']) ? " [{$win['zodiac']}]" : "";
            $html .= "   - 号码 {$win['number']}{$zodiacInfo}: <span style='color: green; font-weight: bold;'>{$win['amount']} 元</span> (赔率 {$win['odds']}){$lotteryTypeInfo}\n";
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
