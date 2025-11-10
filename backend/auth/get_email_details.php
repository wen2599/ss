<?php
// File: backend/auth/get_email_details.php (每条下注单独结算版本)

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
            $decoded = json_decode($row[$key], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row[$key] = $decoded;
            } else {
                $row[$key] = is_string($row[$key]) ? json_decode($row[$key]) : $row[$key];
            }
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

        // 为每个批次计算结算
        if (isset($batch_data['bets']) && is_array($batch_data['bets'])) {
            $lottery_type = $batch_data['lottery_type'] ?? '香港六合彩';
            $lottery_result = $latest_results[$lottery_type] ?? null;
            
            $settlement_data = calculateBatchSettlement($batch_data, $lottery_result);
            $batch_info['settlement'] = $settlement_data;
        }

        $bet_batches[] = $batch_info;
    }

    // --- 5. 如果没有任何批次，使用手动解析 ---
    if (empty($bet_batches)) {
        $manual_batches = parseBetManually($clean_content);
        foreach ($manual_batches as $manual_batch) {
            if (!empty($manual_batch['bets'])) {
                $settlement_data = calculateManualSettlement($manual_batch, $latest_results);
                
                $batch_info = [
                    'batch_id' => 0,
                    'data' => $manual_batch,
                    'ai_model' => 'manual_parser',
                    'settlement' => $settlement_data
                ];
                
                $bet_batches[] = $batch_info;
            }
        }
    }

    // --- 6. 为每条下注单独嵌入结算结果 ---
    $enhanced_content = embedIndividualSettlements($clean_content, $bet_batches, $latest_results);

    // --- 7. 在邮件末尾添加总结算 ---
    $total_settlement = calculateTotalSettlement($bet_batches);
    $enhanced_content .= buildTotalSettlementHtml($total_settlement);

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
 * 手动解析下注信息 - 返回多个批次，每个批次对应一条下注
 */
function parseBetManually(string $text): array {
    $batches = [];
    
    // 解析第一条下注：澳门号码下注
    if (preg_match('/澳门(.+?)各(\d+)#/', $text, $matches)) {
        $numbersText = $matches[1];
        $amount = intval($matches[2]);
        
        preg_match_all('/\d+/', $numbersText, $numberMatches);
        $numbers = $numberMatches[0];
        
        $bets = [];
        $totalAmount = 0;
        foreach ($numbers as $number) {
            $bets[] = [
                'bet_type' => '号码',
                'targets' => [trim($number)],
                'amount' => $amount,
                'raw_text' => "澳门{$number}各{$amount}#"
            ];
            $totalAmount += $amount;
        }
        
        $batches[] = [
            'lottery_type' => '澳门六合彩',
            'issue_number' => '',
            'bets' => $bets,
            'total_amount' => $totalAmount,
            'raw_text' => "澳门{$numbersText}各{$amount}#",
            'description' => "{$amount}元共" . count($numbers) . "个数"
        ];
    }
    
    // 解析第二条下注：生肖下注
    if (preg_match('/([鼠牛虎兔龙蛇马羊猴鸡狗猪])[，,]\s*([鼠牛虎兔龙蛇马羊猴鸡狗猪])数各(\d+)元/', $text, $matches)) {
        $zodiac1 = trim($matches[1]);
        $zodiac2 = trim($matches[2]);
        $amount1 = intval($matches[3]);
        
        // 查找是否有第二条生肖下注
        $amount2 = $amount1;
        if (preg_match('/兔，马数各(\d+)元/', $text, $secondMatches)) {
            $amount2 = intval($secondMatches[1]);
        }
        
        $zodiac1Numbers = getNumbersByZodiac($zodiac1);
        $zodiac2Numbers = getNumbersByZodiac($zodiac2);
        
        $bets = [];
        $totalAmount = 0;
        
        foreach ($zodiac1Numbers as $number) {
            $bets[] = [
                'bet_type' => '生肖',
                'targets' => [$number],
                'amount' => $amount1,
                'raw_text' => "{$zodiac1}号码{$number}各{$amount1}元"
            ];
            $totalAmount += $amount1;
        }
        
        foreach ($zodiac2Numbers as $number) {
            $bets[] = [
                'bet_type' => '生肖',
                'targets' => [$number],
                'amount' => $amount1,
                'raw_text' => "{$zodiac2}号码{$number}各{$amount1}元"
            ];
            $totalAmount += $amount1;
        }
        
        $description = "{$amount1}元共8个数";
        if ($amount1 != $amount2) {
            $description = "{$amount1}元共8个数 {$amount2}元共8个数";
        }
        
        $batches[] = [
            'lottery_type' => '澳门六合彩',
            'issue_number' => '',
            'bets' => $bets,
            'total_amount' => $totalAmount,
            'raw_text' => "{$zodiac1}，{$zodiac2}数各{$amount1}元，兔，马数各{$amount2}元",
            'description' => $description
        ];
    }
    
    // 解析第三条下注：香港号码下注
    if (preg_match('/香港：(.+?)各(\d+)块/', $text, $matches)) {
        $numbersText = $matches[1];
        $amount = intval($matches[2]);
        
        $numbers = explode('.', $numbersText);
        $numbers = array_filter($numbers, function($num) {
            return !empty(trim($num));
        });
        
        $bets = [];
        $totalAmount = 0;
        foreach ($numbers as $number) {
            $bets[] = [
                'bet_type' => '号码',
                'targets' => [trim($number)],
                'amount' => $amount,
                'raw_text' => "香港号码{$number}各{$amount}块"
            ];
            $totalAmount += $amount;
        }
        
        $batches[] = [
            'lottery_type' => '香港六合彩',
            'issue_number' => '',
            'bets' => $bets,
            'total_amount' => $totalAmount,
            'raw_text' => "香港：{$numbersText}各{$amount}块",
            'description' => "{$amount}元共" . count($numbers) . "个数"
        ];
    }
    
    return $batches;
}

/**
 * 为每条下注单独嵌入结算结果
 */
function embedIndividualSettlements(string $content, array $batches, array $latestResults): string {
    $enhancedContent = $content;
    
    foreach ($batches as $batch) {
        $raw_text = $batch['data']['raw_text'] ?? '';
        $settlement = $batch['settlement'] ?? [];
        
        if (!empty($raw_text)) {
            $position = strpos($enhancedContent, $raw_text);
            
            if ($position !== false) {
                $settlementHtml = buildIndividualSettlementHtml($settlement, $batch);
                $insertPosition = $position + strlen($raw_text);
                
                $enhancedContent = substr($enhancedContent, 0, $insertPosition) .
                                  $settlementHtml .
                                  substr($enhancedContent, $insertPosition);
            }
        }
    }
    
    return $enhancedContent;
}

/**
 * 构建单条下注的结算HTML
 */
function buildIndividualSettlementHtml(array $settlement, array $batch): string {
    $hasLotteryData = $settlement['has_lottery_data'] ?? false;
    $totalAmount = $settlement['total_bet_amount'] ?? 0;
    $winningCount = count($settlement['winning_details'] ?? []);
    
    $html = "\n<span style='color: red;'>";
    $html .= "本条下注单结算结果 ";
    
    // 显示下注详情
    $description = $batch['data']['description'] ?? '';
    if (empty($description)) {
        // 自动生成描述
        $betTypes = [];
        foreach ($batch['data']['bets'] ?? [] as $bet) {
            $amount = $bet['amount'];
            $count = count($bet['targets'] ?? []);
            $key = "{$amount}元共{$count}个数";
            $betTypes[$key] = ($betTypes[$key] ?? 0) + $count;
        }
        
        $descriptionParts = [];
        foreach ($betTypes as $type => $count) {
            $descriptionParts[] = $type;
        }
        $description = implode(' ', $descriptionParts);
    }
    
    $html .= $description;
    $html .= "  此条共{$totalAmount}元";
    
    // 显示中奖信息
    if ($hasLotteryData) {
        if ($winningCount > 0) {
            $totalWin = 0;
            foreach ($settlement['winning_details'] as $win) {
                $totalWin += $win['amount'] * $win['odds'];
            }
            $html .= "  中奖{$winningCount}注，赢{$totalWin}元";
        } else {
            $html .= "  未中奖";
        }
    } else {
        $html .= "  等待开奖数据";
    }
    
    $html .= "  <button style='color: blue; border: none; background: none; cursor: pointer;' onclick='editBet(this)'>修改按钮</button>";
    $html .= "</span>";
    
    return $html;
}

/**
 * 构建总结算HTML
 */
function buildTotalSettlementHtml(array $totalSettlement): string {
    $html = "\n\n共计下注{$totalSettlement['total_bet_amount']}元";
    
    if ($totalSettlement['has_lottery_data'] && $totalSettlement['total_win'] > 0) {
        $html .= "  例如有中奖  中奖{$totalSettlement['total_win']}元";
    }
    
    $html .= "\n" . str_repeat("=", 50);
    $html .= "\n🎯 结算结果 (批次 {$totalSettlement['batch_id']})";
    $html .= "\n" . str_repeat("=", 50);
    $html .= "\n💰 总投注金额: {$totalSettlement['total_bet_amount']}";
    
    if ($totalSettlement['has_lottery_data']) {
        $html .= "\n⏳ 中奖详情: {$totalSettlement['winning_count']}";
    } else {
        $html .= "\n⏳ 中奖详情: 等待开奖数据";
    }
    
    $html .= "\n\n📈 不同赔率结算:";
    
    foreach ($totalSettlement['net_profits'] as $odds => $result) {
        $totalWin = $result['total_win'];
        $netProfit = $result['net_profit'];
        $formula = "{$totalSettlement['total_bet_amount']}-{$totalWin}=" . abs($netProfit);
        
        $html .= "\n🔴 赔率 {$odds}:  {$formula}";
    }
    
    $html .= "\n" . str_repeat("=", 50) . "\n";
    
    return $html;
}

/**
 * 计算总结算
 */
function calculateTotalSettlement(array $batches): array {
    $totalBetAmount = 0;
    $totalWinningCount = 0;
    $totalWin = 0;
    $hasLotteryData = false;
    
    $netProfits = [
        45 => ['total_win' => 0, 'net_profit' => 0],
        46 => ['total_win' => 0, 'net_profit' => 0],
        47 => ['total_win' => 0, 'net_profit' => 0]
    ];
    
    foreach ($batches as $batch) {
        $settlement = $batch['settlement'] ?? [];
        $totalBetAmount += $settlement['total_bet_amount'] ?? 0;
        $totalWinningCount += count($settlement['winning_details'] ?? []);
        
        if ($settlement['has_lottery_data'] ?? false) {
            $hasLotteryData = true;
        }
        
        // 计算不同赔率下的收益
        foreach ([45, 46, 47] as $odds) {
            if (isset($settlement['net_profits'][$odds])) {
                $netProfits[$odds]['total_win'] += $settlement['net_profits'][$odds]['total_win'];
                $netProfits[$odds]['net_profit'] += $settlement['net_profits'][$odds]['net_profit'];
            }
        }
    }
    
    // 计算总赢金额（使用赔率45作为示例）
    $totalWin = $netProfits[45]['total_win'];
    
    return [
        'batch_id' => count($batches) > 0 ? $batches[0]['batch_id'] : 0,
        'total_bet_amount' => $totalBetAmount,
        'winning_count' => $totalWinningCount,
        'total_win' => $totalWin,
        'net_profits' => $netProfits,
        'has_lottery_data' => $hasLotteryData
    ];
}

/**
 * 根据生肖获取对应的号码
 */
function getNumbersByZodiac(string $zodiac): array {
    $zodiacMap = [
        '鼠' => ['06', '18', '30', '42'],
        '牛' => ['05', '17', '29', '41'],
        '虎' => ['04', '16', '28', '40'],
        '兔' => ['03', '15', '27', '39'],
        '龙' => ['02', '14', '26', '38'],
        '蛇' => ['01', '13', '25', '37', '49'],
        '马' => ['12', '24', '36', '48'],
        '羊' => ['11', '23', '35', '47'],
        '猴' => ['10', '22', '34', '46'],
        '鸡' => ['09', '21', '33', '45'],
        '狗' => ['08', '20', '32', '44'],
        '猪' => ['07', '19', '31', '43']
    ];
    
    return $zodiacMap[$zodiac] ?? [];
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
 * 计算单个批次的结算结果
 */
function calculateBatchSettlement(array $batchData, ?array $lotteryResult = null): array {
    $settlement = [
        'total_bet_amount' => 0,
        'winning_details' => [],
        'net_profits' => [],
        'has_lottery_data' => !is_null($lotteryResult) && 
                             isset($lotteryResult['winning_numbers']) && 
                             is_array($lotteryResult['winning_numbers']) &&
                             !empty($lotteryResult['winning_numbers'])
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

                if ($settlement['has_lottery_data']) {
                    $winningNumbers = $lotteryResult['winning_numbers'];
                    
                    if ($betType === '特码' || $betType === '号码' || $betType === '生肖') {
                        $specialNumber = end($winningNumbers);
                        if (strval(trim($target)) === strval(trim($specialNumber))) {
                            $winningBets[] = [
                                'number' => $target,
                                'amount' => $amount,
                                'odds' => 45
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
            'net_profit' => $netProfit
        ];
    }

    return $settlement;
}

/**
 * 计算手动解析的结算
 */
function calculateManualSettlement(array $manualData, array $latestResults): array {
    $settlement = [
        'total_bet_amount' => $manualData['total_amount'],
        'winning_details' => [],
        'net_profits' => [],
        'has_lottery_data' => !empty($latestResults)
    ];

    $winningBets = [];
    $lotteryType = $manualData['lottery_type'] ?? '香港六合彩';

    // 选择对应的开奖结果
    $result = null;
    if (isset($latestResults[$lotteryType])) {
        $result = $latestResults[$lotteryType];
    } elseif ($lotteryType === '澳门六合彩' && isset($latestResults['新澳门六合彩'])) {
        $result = $latestResults['新澳门六合彩'];
    } elseif ($lotteryType === '澳门六合彩' && isset($latestResults['老澳门六合彩'])) {
        $result = $latestResults['老澳门六合彩'];
    } else {
        $result = reset($latestResults) ?: null;
    }

    foreach ($manualData['bets'] as $bet) {
        $amount = $bet['amount'];
        $targets = $bet['targets'];

        foreach ($targets as $target) {
            if ($result && isset($result['winning_numbers']) && is_array($result['winning_numbers'])) {
                $winningNumbers = $result['winning_numbers'];
                $specialNumber = end($winningNumbers);
                
                if (strval(trim($target)) === strval(trim($specialNumber))) {
                    $winningBets[] = [
                        'number' => $target,
                        'amount' => $amount,
                        'odds' => 45
                    ];
                }
            }
        }
    }

    $settlement['winning_details'] = $winningBets;

    // 计算不同赔率下的净收益
    $oddsList = [45, 46, 47];
    foreach ($oddsList as $odds) {
        $totalWin = 0;
        foreach ($winningBets as $win) {
            $totalWin += $win['amount'] * $odds;
        }
        $netProfit = $totalWin - $manualData['total_amount'];
        $settlement['net_profits'][$odds] = [
            'total_win' => $totalWin,
            'net_profit' => $netProfit
        ];
    }

    return $settlement;
}
?>
