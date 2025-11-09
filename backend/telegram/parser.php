<?php
// File: backend/telegram/parser.php (Enhanced Version)

/**
 * 解析来自 Telegram 频道的开奖信息文本。
 *
 * @param string $text 完整的频道消息文本。
 * @return array|null 解析成功则返回包含结构化数据的数组，否则返回 null。
 */
function parse_lottery_data($text) {
    // 定义一个映射，将 emoji 波色转换为文字
    $color_map = [
        '🔴' => '红波',
        '🟢' => '绿波',
        '🔵' => '蓝波',
    ];

    // 正则表达式，用于捕获开奖公告的三个主要部分：
    // 1. (.*?) - 开奖类型 (非贪婪)
    // 2. (\d+) - 期号
    // 3. ([\s\S]*) - 开奖结果后的所有内容
    $pattern = '/(新澳门六合彩|香港六合彩|老澳.*?)第:(\d+)\s*期开奖结果:\s*([\s\S]*)/u';

    if (!preg_match($pattern, $text, $matches)) {
        return null; // 如果连基本格式都不匹配，直接返回
    }

    $lottery_type_raw = $matches[1];
    $issue_number = $matches[2];
    $results_block = trim($matches[3]);

    // 确定标准的开奖类型名称
    $lottery_type = (strpos($lottery_type_raw, '老澳') !== false) ? '老澳门六合彩' : trim($lottery_type_raw);

    // 将结果块按行分割，并过滤掉空行
    $lines = array_values(array_filter(explode("\n", $results_block), 'trim'));

    // 至少需要3行数据（号码、生肖、波色）
    if (count($lines) < 3) {
        return null;
    }

    // 分别提取号码、生肖和波色
    $winning_numbers = preg_split('/\s+/', trim($lines[0]));
    $zodiac_signs = preg_split('/\s+/', trim($lines[1]));
    $raw_colors = preg_split('/\s+/', trim($lines[2]));

    // 检查三行的数据量是否一致，如果不一致则数据格式有问题
    if (count($winning_numbers) !== count($zodiac_signs) || count($winning_numbers) !== count($raw_colors)) {
        return null;
    }

    // 将 emoji 波色转换为文字
    $colors = array_map(function($emoji) use ($color_map) {
        return $color_map[$emoji] ?? '未知';
    }, $raw_colors);

    // 检查转换后的波色数组是否包含了“未知”，如果包含说明有无法识别的 emoji
    if (in_array('未知', $colors)) {
        return null;
    }

    // 所有数据都成功解析，返回结构化数组
    return [
        'lottery_type' => $lottery_type,
        'issue_number' => $issue_number,
        'winning_numbers' => $winning_numbers,
        'zodiac_signs' => $zodiac_signs,
        'colors' => $colors,
        'drawing_date' => date('Y-m-d') // 使用当前服务器日期作为开奖日期
    ];
}