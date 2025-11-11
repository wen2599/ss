<?php
// File: backend/auth/get_email_details.php (完整修复版)

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

    // --- 获取用户赔率模板 ---
    $stmt_odds = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
    $stmt_odds->execute([$user_id]);
    $userOddsTemplate = $stmt_odds->fetch(PDO::FETCH_ASSOC);

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
            $decoded = json_decode($row[$key], true);
            $row[$key] = $decoded ?: [];
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

        // --- 5. 为每个批次计算结算（使用实际开奖结果和用户赔率模板）---
        if (isset($batch_data['bets']) && is_array($batch_data['bets'])) {
            $lottery_type = $batch_data['lottery_type'] ?? '香港六合彩';
            $lottery_result = $latest_results[$lottery_type] ?? null;

            $settlement_data = calculateBatchSettlement($batch_data, $lottery_result, $userOddsTemplate);
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
        require_once __DIR__ . '/../helpers/manual_parser.php';
        $manual_data = parseBetManually($clean_content);
        if (!empty($manual_data['bets'])) {
            // 为手动解析的数据计算结算
            $settlement_data = calculateManualSettlement($manual_data, $latest_results, $userOddsTemplate);

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
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error: ' . $e->getMessage()]);
}

/**
 * 计算单个批次的结算结果 - 使用用户赔率模板
 */
function calculateBatchSettlement(array $batchData, ?array $lotteryResult = null, ?array $userOddsTemplate = null): array {
    $settlement = [
        'total_bet_amount' => 0,
        'winning_details' => [],
        'net_profits' => [],
        'summary' => '',
        'timestamp' => date('Y-m-d H:i:s'),
        'has_lottery_data' => !is_null($lotteryResult) &&
                             isset($lotteryResult['winning_numbers']) &&
                             is_array($lotteryResult['winning_numbers']) &&
                             !empty($lotteryResult['winning_numbers']),
        'used_odds' => null
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

                // 如果有有效的开奖结果，进行实际结算计算
                if ($settlement['has_lottery_data']) {
                    $winningNumbers = $lotteryResult['winning_numbers'];

                    // 根据下注类型获取对应的赔率
                    $odds = getUserOddsForBetType($betType, $userOddsTemplate);
                    $settlement['used_odds'] = $odds;

                    if ($odds === null) {
                        continue; // 如果没有设置该玩法的赔率，跳过结算
                    }

                    if ($betType === '特码' || $betType === '号码') {
                        // 特码玩法：只对比特码（最后一个号码）
                        $specialNumber = end($winningNumbers);
                        if (strval(trim($target)) === strval(trim($specialNumber))) {
                            $winningBets[] = [
                                'number' => $target,
                                'amount' => $amount,
                                'odds' => $odds,
                                'bet_type' => $betType
                            ];
                        }
                    } elseif ($betType === '平码') {
                        // 平码玩法：对比所有号码
                        $winningNumbersStr = array_map('strval', array_map('trim', $winningNumbers));
                        if (in_array(strval(trim($target)), $winningNumbersStr)) {
                            $winningBets[] = [
                                'number' => $target,
                                'amount' => $amount,
                                'odds' => $odds,
                                'bet_type' => $betType
                            ];
                        }
                    } elseif ($betType === '生肖') {
                        // 生肖玩法：根据号码对应的生肖来判断
                        $targetZodiac = getZodiacByNumber($target);
                        if ($targetZodiac && isset($lotteryResult['zodiac_signs']) && is_array($lotteryResult['zodiac_signs'])) {
                            $zodiacsStr = array_map('strval', array_map('trim', $lotteryResult['zodiac_signs']));
                            if (in_array($targetZodiac, $zodiacsStr)) {
                                $winningBets[] = [
                                    'number' => $target,
                                    'amount' => $amount,
                                    'odds' => $odds,
                                    'bet_type' => $betType,
                                    'zodiac' => $targetZodiac
                                ];
                            }
                        }
                    }
                    // 其他玩法类型可以在这里扩展
                }
            }
        }
    }

    $settlement['total_bet_amount'] = $totalBet;
    $settlement['winning_details'] = $winningBets;

    // 计算净收益（使用用户设置的单一赔率）
    $totalWin = 0;
    foreach ($winningBets as $win) {
        $totalWin += $win['amount'] * $win['odds'];
    }
    $netProfit = $totalWin - $totalBet;
    
    $settlement['net_profits'] = [
        'total_win' => $totalWin,
        'net_profit' => $netProfit,
        'is_profit' => $netProfit >= 0,
        'odds' => $settlement['used_odds']
    ];

    $winCount = count($winningBets);
    if ($settlement['has_lottery_data']) {
        $specialNumber = end($lotteryResult['winning_numbers']);
        $oddsInfo = $settlement['used_odds'] ? " (赔率: {$settlement['used_odds']})" : "";
        $settlement['summary'] = "总下注 {$totalBet} 元，中奖 {$winCount} 注{$oddsInfo}，特码: {$specialNumber}";
    } else {
        $oddsInfo = $settlement['used_odds'] ? " (赔率: {$settlement['used_odds']})" : "";
        $settlement['summary'] = "总下注 {$totalBet} 元{$oddsInfo}（等待开奖数据）";
    }

    return $settlement;
}

/**
 * 根据下注类型获取用户设置的赔率
 */
function getUserOddsForBetType(string $betType, ?array $userOddsTemplate): ?float {
    if (!$userOddsTemplate) {
        return null;
    }

    $oddsMapping = [
        '特码' => 'special_code_odds',
        '号码' => 'special_code_odds',
        '平码' => 'flat_special_odds',
        '平特' => 'flat_special_odds',
        '串码' => 'serial_code_odds',
        '连肖' => 'even_xiao_odds',
        '六肖' => 'six_xiao_odds',
        '大小' => 'size_single_double_odds',
        '单双' => 'size_single_double_odds'
    ];

    $templateKey = $oddsMapping[$betType] ?? null;
    if ($templateKey && isset($userOddsTemplate[$templateKey]) && $userOddsTemplate[$templateKey] !== null) {
        return floatval($userOddsTemplate[$templateKey]);
    }

    return null;
}

/**
 * 计算手动解析的结算
 */
function calculateManualSettlement(array $manualData, array $latestResults, ?array $userOddsTemplate = null): array {
    $settlement = [
        'total_bet_amount' => $manualData['total_amount'],
        'winning_details' => [],
        'net_profits' => [],
        'summary' => '',
        'timestamp' => date('Y-m-d H:i:s'),
        'has_lottery_data' => !empty($latestResults),
        'used_odds' => null
    ];

    $winningBets = [];

    // 使用所有彩票类型的结果进行结算
    foreach ($manualData['bets'] as $bet) {
        $amount = $bet['amount'];
        $betType = $bet['bet_type'];
        $targets = $bet['targets'];
        $lotteryType = $bet['lottery_type'] ?? '香港六合彩';

        // 选择对应的开奖结果
        $result = null;
        if (isset($latestResults[$lotteryType])) {
            $result = $latestResults[$lotteryType];
        } elseif ($lotteryType === '澳门六合彩' && isset($latestResults['新澳门六合彩'])) {
            $result = $latestResults['新澳门六合彩'];
        } elseif ($lotteryType === '澳门六合彩' && isset($latestResults['老澳门六合彩'])) {
            $result = $latestResults['老澳门六合彩'];
        } else {
            // 如果没有对应的开奖结果，使用第一个可用的结果
            $result = reset($latestResults) ?: null;
        }

        // 获取用户设置的赔率
        $odds = getUserOddsForBetType($betType, $userOddsTemplate);
        $settlement['used_odds'] = $odds;

        foreach ($targets as $target) {
            if ($result && isset($result['winning_numbers']) && is_array($result['winning_numbers'])) {
                $winningNumbers = $result['winning_numbers'];

                if ($betType === '号码' || $betType === '特码' || $betType === '生肖') {
                    // 特码玩法：对比特码（最后一个号码）
                    $specialNumber = end($winningNumbers);
                    if (strval(trim($target)) === strval(trim($specialNumber))) {
                        $zodiacInfo = isset($bet['zodiac']) ? " [{$bet['zodiac']}]" : "";
                        $winningBets[] = [
                            'number' => $target,
                            'amount' => $amount,
                            'odds' => $odds ?: 45, // 如果没有设置赔率，使用默认45
                            'bet_type' => $betType,
                            'lottery_type' => $result['lottery_type'],
                            'zodiac' => $bet['zodiac'] ?? null
                        ];
                    }
                }
            }
        }
    }

    $settlement['winning_details'] = $winningBets;

    // 计算净收益
    $totalWin = 0;
    foreach ($winningBets as $win) {
        $totalWin += $win['amount'] * $win['odds'];
    }
    $netProfit = $totalWin - $manualData['total_amount'];
    
    $settlement['net_profits'] = [
        'total_win' => $totalWin,
        'net_profit' => $netProfit,
        'is_profit' => $netProfit >= 0,
        'odds' => $settlement['used_odds']
    ];

    $winCount = count($winningBets);
    if (!empty($latestResults)) {
        $oddsInfo = $settlement['used_odds'] ? " (赔率: {$settlement['used_odds']})" : "";
        $settlement['summary'] = "总下注 {$manualData['total_amount']} 元，中奖 {$winCount} 注{$oddsInfo}";
    } else {
        $oddsInfo = $settlement['used_odds'] ? " (赔率: {$settlement['used_odds']})" : "";
        $settlement['summary'] = "总下注 {$manualData['total_amount']} 元{$oddsInfo}（等待开奖数据）";
    }

    return $settlement;
}

/**
 * 根据号码获取生肖
 */
function getZodiacByNumber($number): ?string {
    $zodiacMap = [
        '01' => '蛇', '13' => '蛇', '25' => '蛇', '37' => '蛇', '49' => '蛇',
        '02' => '龙', '14' => '龙', '26' => '龙', '38' => '龙',
        '03' => '兔', '15' => '兔', '27' => '兔', '39' => '兔',
        '04' => '虎', '16' => '虎', '28' => '虎', '40' => '虎',
        '05' => '牛', '17' => '牛', '29' => '牛', '41' => '牛',
        '06' => '鼠', '18' => '鼠', '30' => '鼠', '42' => '鼠',
        '07' => '猪', '19' => '猪', '31' => '猪', '43' => '猪',
        '08' => '狗', '20' => '狗', '32' => '狗', '44' => '狗',
        '09' => '鸡', '21' => '鸡', '33' => '鸡', '45' => '鸡',
        '10' => '猴', '22' => '猴', '34' => '猴', '46' => '猴',
        '11' => '羊', '23' => '羊', '35' => '羊', '47' => '羊',
        '12' => '马', '24' => '马', '36' => '马', '48' => '马'
    ];

    $numberPadded = str_pad(strval(trim($number)), 2, '0', STR_PAD_LEFT);
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
    $settlementHtml = buildPlainSettlementHtml($settlement, $batchId);

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
    $settlementHtml = buildPlainSettlementHtml($settlement, 0);
    return $content . "\n\n" . $settlementHtml;
}

/**
 * 构建纯文本结算HTML
 */
function buildPlainSettlementHtml(array $settlement, int $batchId): string {
    $html = "\n\n" . str_repeat("=", 50) . "\n";
    $html .= "🎯 结算结果 (批次 {$batchId})\n";
    $html .= str_repeat("=", 50) . "\n";

    // 总下注金额
    $html .= "💰 总投注金额: {$settlement['total_bet_amount']} 元\n";

    // 中奖详情
    if (!empty($settlement['winning_details'])) {
        $html .= "🎊 中奖详情:\n";
        foreach ($settlement['winning_details'] as $win) {
            $lotteryTypeInfo = isset($win['lottery_type']) ? " ({$win['lottery_type']})" : "";
            $zodiacInfo = isset($win['zodiac']) ? " [{$win['zodiac']}]" : "";
            $oddsInfo = isset($win['odds']) ? " (赔率 {$win['odds']})" : "";
            $html .= "   - 号码 {$win['number']}{$zodiacInfo}: {$win['amount']} 元{$oddsInfo}{$lotteryTypeInfo}\n";
        }
    } else {
        if ($settlement['has_lottery_data']) {
            $html .= "❌ 中奖详情: 未中奖\n";
        } else {
            $html .= "⏳ 中奖详情: 等待开奖数据\n";
        }
    }

    // 结算结果
    $html .= "\n📈 结算结果:\n";
    if ($settlement['net_profits'] && isset($settlement['net_profits']['net_profit'])) {
        $netProfit = $settlement['net_profits']['net_profit'];
        $isProfit = $settlement['net_profits']['is_profit'];
        $oddsInfo = $settlement['used_odds'] ? " (赔率: {$settlement['used_odds']})" : "";
        
        $emoji = $isProfit ? '🟢' : '🔴';
        $profitText = $isProfit ? "盈利" : "亏损";
        $netAmount = abs($netProfit);

        $html .= "{$emoji} {$profitText} {$netAmount} 元{$oddsInfo}\n";
    }

    // 添加开奖数据信息
    if (!$settlement['has_lottery_data']) {
        $html .= "\n⚠️  注意: 当前无开奖数据，以上为模拟结算结果\n";
    }

    $html .= str_repeat("=", 50) . "\n";

    return $html;
}

/**
 * 构建结算HTML - 保留原函数兼容性
 */
function buildSettlementHtml(array $settlement, int $batchId): string {
    return buildPlainSettlementHtml($settlement, $batchId);
}
?>