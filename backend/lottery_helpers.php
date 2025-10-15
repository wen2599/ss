<?php

const COLOR_WAVES = [
    'red'   => ['01', '02', '07', '08', '12', '13', '18', '19', '23', '24', '29', '30', '34', '35', '40', '45', '46'],
    'blue'  => ['03', '04', '09', '10', '14', '15', '20', '25', '26', '31', '36', '37', '41', '42', '47', '48'],
    'green' => ['05', '06', '11', '16', '17', '21', '22', '27', '28', '32', '33', '38', '39', '43', '44', '49'],
];

const COLOR_EMOJI_MAP = [
    'red'   => '🔴',
    'blue'  => '🔵',
    'green' => '🟢',
];

/**
 * Determines the color wave for a given number.
 *
 * @param string $number The two-digit number string.
 * @return string The color name ('red', 'blue', 'green') or 'unknown'.
 */
function get_color_for_number($number) {
    foreach (COLOR_WAVES as $color => $numbers) {
        if (in_array($number, $numbers)) {
            return $color;
        }
    }
    return 'unknown';
}

/**
 * Gets the corresponding emoji for a color name.
 *
 * @param string $color The color name.
 * @return string The emoji.
 */
function get_emoji_for_color($color) {
    return COLOR_EMOJI_MAP[$color] ?? '❓';
}

const ZODIAC_NUMBERS = [
    '蛇' => ['01', '13', '25', '37', '49'],
    '龙' => ['02', '14', '26', '38'],
    '兔' => ['03', '15', '27', '39'],
    '虎' => ['04', '16', '28', '40'],
    '牛' => ['05', '17', '29', '41'],
    '鼠' => ['06', '18', '30', '42'],
    '猪' => ['07', '19', '31', '43'],
    '狗' => ['08', '20', '32', '44'],
    '鸡' => ['09', '21', '33', '45'],
    '猴' => ['10', '22', '34', '46'],
    '羊' => ['11', '23', '35', '47'],
    '马' => ['12', '24', '36', '48'],
];

/**
 * Determines the zodiac sign for a given number.
 *
 * @param string $number The two-digit number string.
 * @return string The zodiac sign or '未知'.
 */
function get_zodiac_for_number($number) {
    foreach (ZODIAC_NUMBERS as $zodiac => $numbers) {
        if (in_array($number, $numbers)) {
            return $zodiac;
        }
    }
    return '未知';
}
?>