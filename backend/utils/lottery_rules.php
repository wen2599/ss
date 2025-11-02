<?php
// backend/utils/lottery_rules.php

class LotteryHelper {

    private static $numberProperties = null;

    // 初始化所有号码的属性
    private static function init() {
        if (self::$numberProperties !== null) {
            return;
        }
        
        // 定义颜色映射
        $colors = [
            'red'   => [1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46],
            'blue'  => [3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48],
            'green' => [5, 6, 11, 16, 17, 21, 22, 27, 28, 32, 33, 38, 39, 43, 44, 49],
        ];
        
        // 定义生肖映射
        $zodiacs = [
            '鼠' => [6, 18, 30, 42], '牛' => [5, 17, 29, 41], '虎' => [4, 16, 28, 40],
            '兔' => [3, 15, 27, 39], '龙' => [2, 14, 26, 38], '蛇' => [1, 13, 25, 37, 49],
            '马' => [12, 24, 36, 48], '羊' => [11, 23, 35, 47], '猴' => [10, 22, 34, 46],
            '鸡' => [9, 21, 33, 45], '狗' => [8, 20, 32, 44], '猪' => [7, 19, 31, 43],
        ];

        self::$numberProperties = [];
        for ($i = 1; $i <= 49; $i++) {
            $props = [];
            // 获取颜色
            foreach ($colors as $color => $numbers) {
                if (in_array($i, $numbers)) {
                    $props['color'] = $color;
                    break;
                }
            }
            // 获取生肖
            foreach ($zodiacs as $zodiac => $numbers) {
                if (in_array($i, $numbers)) {
                    $props['zodiac'] = $zodiac;
                    break;
                }
            }
            // 获取单双
            $props['parity'] = ($i % 2 == 0) ? '双' : '单';

            self::$numberProperties[$i] = $props;
        }
    }

    /**
     * 获取单个数字的所有属性
     * @param int|string $number
     * @return array|null 包含 color, zodiac, parity 的数组
     */
    public static function getProperties($number) {
        self::init();
        $num = (int)$number;
        return self::$numberProperties[$num] ?? null;
    }

    /**
     * 获取单个数字的生肖
     * @param int|string $number
     * @return string|null
     */
    public static function getZodiac($number) {
        $props = self::getProperties($number);
        return $props['zodiac'] ?? null;
    }

    /**
     * 获取单个数字的颜色
     * @param int|string $number
     * @return string|null
     */
    public static function getColor($number) {
        $props = self::getProperties($number);
        return $props['color'] ?? null;
    }
    
    /**
     * 获取单个数字的单双
     * @param int|string $number
     * @return string|null
     */
    public static function getParity($number) {
        $props = self::getProperties($number);
        return $props['parity'] ?? null;
    }
}