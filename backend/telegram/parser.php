<?php
// File: backend/telegram/parser.php (Batch and Smart Parser Version)

// 引入我们的规则文件
require_once __DIR__ . '/../lottery/rules.php';

/**
 * 智能地解析来自 Telegram 频道的开奖信息文本，支持单条消息中包含多个开奖公告。
 *
 * @param string $text 完整的频道消息文本。
 * @return array 返回一个包含零个或多个解析结果的数组。
 */
function parse_lottery_data_batch(string $text): array {
    $color_map_emoji = ['🔴' => '红波', '🟢' => '绿波', '🔵' => '蓝波'];
    $all_results = [];

    // 1. 将整个文本块按 "一波中" 或类似的公告开头进行分割
    // 使用 preg_split 并保留分隔符，这样每个块都是完整的
    $blocks = preg_split('/(一波中|.*?第:.*期开奖结果:)/u', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    
    // 组合分隔符和它们对应的内容块
    for ($i = 0; $i < count($blocks); $i += 2) {
        if (!isset($blocks[$i+1])) continue;
        
        $block_text = $blocks[$i] . $blocks[$i+1];

        // 2. 对每个独立的公告块进行解析
        $pattern = '/(新澳门六合彩|香港六合彩|老澳.*?)第:(\d+)\s*期开奖结果:\s*([\s\S]*)/u';

        if (!preg_match($pattern, $block_text, $matches)) {
            continue; // 如果这个块不匹配，跳到下一个
        }

        $lottery_type_raw = $matches[1];
        $issue_number = $matches[2];
        $results_block_text = trim($matches[3]);
        
        $lottery_type = (strpos($lottery_type_raw, '老澳') !== false) ? '老澳门六合彩' : trim($lottery_type_raw);
        $lines = array_values(array_filter(explode("\n", $results_block_text), 'trim'));

        // 至少需要号码行
        if (count($lines) < 1) continue;

        $winning_numbers = preg_split('/\s+/', trim($lines[0]));

        // 3. 智能推断或解析生肖和波色
        $zodiac_signs = [];
        $colors = [];
        
        $has_zodiac_line = isset($lines[1]) && !preg_match('/[🔴🟢🔵]/u', $lines[1]);
        $has_color_line = isset($lines[2]) || (isset($lines[1]) && preg_match('/[🔴🟢🔵]/u', $lines[1]));

        // 优先从文本中解析
        if ($has_zodiac_line) {
            $zodiac_signs = preg_split('/\s+/', trim($lines[1]));
        }
        if ($has_color_line) {
            $color_line = isset($lines[2]) ? $lines[2] : $lines[1];
            $raw_colors = preg_split('/\s+/', trim($color_line));
            $colors = array_map(fn($emoji) => $color_map_emoji[$emoji] ?? '未知', $raw_colors);
        }

        // 4. 如果缺少信息，则使用规则进行推断
        foreach ($winning_numbers as $index => $number) {
            if (empty($zodiac_signs[$index])) {
                $zodiac_signs[$index] = get_zodiac_by_number($number) ?? '未知';
            }
            if (empty($colors[$index]) || $colors[$index] === '未知') {
                $colors[$index] = get_color_by_number($number) ?? '未知';
            }
        }
        
        // 5. 数据校验
        if (count($winning_numbers) > 0 && count($winning_numbers) === count($zodiac_signs) && count($winning_numbers) === count($colors)) {
            $all_results[] = [
                'lottery_type' => $lottery_type,
                'issue_number' => $issue_number,
                'winning_numbers' => $winning_numbers,
                'zodiac_signs' => $zodiac_signs,
                'colors' => $colors,
                'drawing_date' => date('Y-m-d')
            ];
        }
    }

    return $all_results;
}
?>