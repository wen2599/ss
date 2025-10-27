<?php

declare(strict_types=1);

// backend/settlement_rules.php

/**
 * 根据号码获取对应的生肖。
 * !!重要提醒!!：此生肖-号码映射基于当前通用年份（例如龙年）。
 * 在实际业务中，生肖的起始号码每年都会有变动，请务必根据当年的实际规则来更新此映射表。
 * 例如，如果今年是鼠年，那么1号可能对应鼠；如果是牛年，1号可能对应牛。
 *
 * @param int $number 号码 (1-49)
 * @return string|null 对应的生肖，如果号码无效则返回null。
 */
function get_zodiac_for_number(int $number): ?string
{
    if ($number < 1 || $number > 49) {
        return null;
    }

    // 这里的映射需要根据实际年份的生肖规则进行调整
    // 示例映射（以某个特定年份为基准，需要根据实际生肖表更新）
    $zodiacs_map = [
        '鼠' => [1, 13, 25, 37, 49],
        '牛' => [12, 24, 36, 48],
        '虎' => [11, 23, 35, 47],
        '兔' => [10, 22, 34, 46],
        '龙' => [9, 21, 33, 45],
        '蛇' => [8, 20, 32, 44],
        '马' => [7, 19, 31, 43],
        '羊' => [6, 18, 30, 42],
        '猴' => [5, 17, 29, 41],
        '鸡' => [4, 16, 28, 40],
        '狗' => [3, 15, 27, 39],
        '猪' => [2, 14, 26, 38],
    ];

    foreach ($zodiacs_map as $zodiac => $numbers_in_zodiac) {
        if (in_array($number, $numbers_in_zodiac)) {
            return $zodiac;
        }
    }
    return null; // 对于 1-49 的有效号码，理论上不应到达这里，除非映射不完整。
}

/**
 * 核心结算函数：根据投注信息和开奖结果计算输赢金额。
 * @param array $bet 单条投注信息，包含 ['type', 'content', 'amount', 'odds']。
 * @param array $winning_numbers 开奖号码数组 (7个数字)，前6个为平码，第7个为特码。
 * @return float 输赢金额。赢了是正数，输了是0。
 */
function calculate_winnings(array $bet, array $winning_numbers): float
{
    $odds = floatval($bet['odds'] ?? 0);
    $amount = floatval($bet['amount'] ?? 0);
    if ($odds <= 0 || $amount <= 0) {
        return 0.0; // 无效赔率或金额，不计算输赢
    }

    // 确保开奖号码是整数类型
    $winning_numbers_int = array_map('intval', $winning_numbers);

    $normal_numbers = array_slice($winning_numbers_int, 0, 6);
    $special_number = $winning_numbers_int[6] ?? null;

    switch (strtolower(trim($bet['type']))) {
        case '特码':
            $bet_number = intval($bet['content']);
            return ($bet_number === $special_number) ? $amount * $odds : 0.0;

        case '平码':
            $bet_number = intval($bet['content']);
            return in_array($bet_number, $normal_numbers) ? $amount * $odds : 0.0;

        case '三中二':
            $bet_numbers_str = explode(',', $bet['content']);
            $bet_numbers = array_map('intval', $bet_numbers_str);
            
            // 确保投注号码数量正确
            if (count($bet_numbers) !== 3) return 0.0;

            $hit_count = count(array_intersect($bet_numbers, $normal_numbers));
            // 规则：投注3个号码，命中至少2个平码即中奖。
            return ($hit_count >= 2) ? $amount * $odds : 0.0;

        case '平一肖':
            $bet_zodiac = trim($bet['content']);
            foreach ($normal_numbers as $num) { // 只计算平码中的生肖
                if (get_zodiac_for_number($num) === $bet_zodiac) {
                    return $amount * $odds; // 命中一个平码生肖即中奖
                }
            }
            return 0.0;

        case '合肖':
            $bet_zodiacs = array_map('trim', explode(',', $bet['content']));
            // 确保特码存在且能获取到生肖
            if ($special_number === null) return 0.0;
            $special_zodiac = get_zodiac_for_number($special_number);
            
            return ($special_zodiac !== null && in_array($special_zodiac, $bet_zodiacs)) ? $amount * $odds : 0.0;

        case '连肖':
            $bet_zodiacs = array_map('trim', explode(',', $bet['content']));
            
            // 获取所有开奖号码（包括特码）对应的生肖
            $winning_zodiacs = array_unique(array_filter(array_map(fn($num) => get_zodiac_for_number($num), $winning_numbers_int)));
            
            // 检查投注的每个生肖是否都在中奖生肖列表中
            $all_hit = true;
            foreach ($bet_zodiacs as $b_zodiac) {
                if (!in_array($b_zodiac, $winning_zodiacs)) {
                    $all_hit = false;
                    break;
                }
            }
            return $all_hit ? $amount * $odds : 0.0;

        default:
            // 对于未知的投注类型，不计算输赢。
            error_log("未知投注类型: " . $bet['type']);
            return 0.0;
    }
}
