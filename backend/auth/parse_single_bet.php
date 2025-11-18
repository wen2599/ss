<?php
// File: backend/auth/parse_single_bet.php (修复版，支持彩票类型参数)

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email_id = $input['email_id'] ?? null;
$bet_text = $input['bet_text'] ?? null;
$line_number = $input['line_number'] ?? null;
$lottery_type = $input['lottery_type'] ?? '香港六合彩'; // 新增参数

// 参数验证
if (empty($email_id) || !is_numeric($email_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid Email ID is required.']);
    exit;
}

if (empty($bet_text) || !is_string($bet_text)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid bet text is required.']);
    exit;
}

try {
    $pdo = get_db_connection();
    $user_id = $_SESSION['user_id'];

    // 验证邮件属于当前用户
    $stmt = $pdo->prepare("SELECT id FROM raw_emails WHERE id = ? AND user_id = ?");
    $stmt->execute([intval($email_id), $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. Email not found.']);
        exit;
    }

    // 获取用户赔率模板
    $stmt_odds = $pdo->prepare("SELECT * FROM user_odds_templates WHERE user_id = ?");
    $stmt_odds->execute([$user_id]);
    $userOddsTemplate = $stmt_odds->fetch(PDO::FETCH_ASSOC);

    // 如果没有赔率模板，设置为空数组
    if (!$userOddsTemplate) {
        $userOddsTemplate = [];
    }

    // 获取最新开奖结果
    $sql_latest_results = "
        SELECT r1.* FROM lottery_results r1
        JOIN (SELECT lottery_type, MAX(id) AS max_id FROM lottery_results GROUP BY lottery_type) r2
        ON r1.lottery_type = r2.lottery_type AND r1.id = r2.max_id
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

    // 解析单条下注
    $parse_result = parseSingleBetText($bet_text, $latest_results, $userOddsTemplate, $lottery_type);

    // 保存到数据库
    $bet_data_json = json_encode([
        'raw_text' => $bet_text,
        'line_number' => $line_number ? intval($line_number) : null,
        'bets' => $parse_result['bets'],
        'total_amount' => $parse_result['total_amount'],
        'lottery_type' => $parse_result['lottery_type'],
        'settlement' => $parse_result['settlement']
    ]);

    $stmt_insert = $pdo->prepare("
        INSERT INTO parsed_bets (email_id, bet_data_json, ai_model_used, line_number)
        VALUES (?, ?, 'single_line_parser', ?)
    ");
    $stmt_insert->execute([
        intval($email_id),
        $bet_data_json,
        $line_number ? intval($line_number) : null
    ]);

    $batch_id = $pdo->lastInsertId();

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'batch_id' => $batch_id,
            'parse_result' => $parse_result,
            'line_number' => $line_number
        ]
    ]);

} catch (Throwable $e) {
    error_log("Error parsing single bet: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '解析失败: ' . $e->getMessage()]);
}

// 在 parseSingleBetText 函数中，确保正确计算总下注金额
function parseSingleBetText(string $text, array $latest_results, ?array $userOddsTemplate = null, string $lottery_type = '香港六合彩'): array {
    require_once __DIR__ . '/../helpers/manual_parser.php';

    // 先尝试手动解析
    $manual_data = parseBetManually($text);

    // 如果手动解析没有结果，尝试AI解析
    if (empty($manual_data['bets'])) {
        require_once __DIR__ . '/../ai_helper.php';
        // 使用专门的单条下注AI解析函数
        $ai_result = analyzeSingleBetWithAI($text, $lottery_type);

        if ($ai_result['success'] && isset($ai_result['data'])) {
            $manual_data = $ai_result['data'];
        }
    }

    // 确保彩票类型正确设置
    $manual_data['lottery_type'] = $lottery_type;

    // 重新计算总下注金额，确保准确性
    $total_amount = 0;
    if (isset($manual_data['bets']) && is_array($manual_data['bets'])) {
        foreach ($manual_data['bets'] as &$bet) {
            $amount = floatval($bet['amount'] ?? 0);
            $targets = $bet['targets'] ?? [];

            if ($amount > 0) {
                // 对于特码、号码等玩法，每个目标都算一次下注
                if ($bet['bet_type'] === '特码' || $bet['bet_type'] === '号码' || $bet['bet_type'] === '平码') {
                    $bet_total = $amount * (is_array($targets) ? count($targets) : 1);
                } else {
                    // 对于六肖等组合玩法，只算一次下注
                    $bet_total = $amount;
                }
                $total_amount += $bet_total;
            }

            // 确保每个下注都有彩票类型
            if (!isset($bet['lottery_type'])) {
                $bet['lottery_type'] = $manual_data['lottery_type'];
            }
        }
        $manual_data['total_amount'] = $total_amount;
    }

    // 计算结算
    $settlement_data = calculateManualSettlementDirect($manual_data, $latest_results, $userOddsTemplate);

    return [
        'bets' => $manual_data['bets'] ?? [],
        'total_amount' => $manual_data['total_amount'] ?? 0,
        'lottery_type' => $manual_data['lottery_type'] ?? $lottery_type,
        'settlement' => $settlement_data,
        'raw_text' => $text
    ];
}

/**
 * 计算手动解析的结算（直接版本）
 */
function calculateManualSettlementDirect(array $manualData, array $latestResults, ?array $userOddsTemplate = null): array {
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
?>