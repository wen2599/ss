<?php

/**
 * Class GameData
 *
 * This class holds the static data for the Mark Six lottery game logic,
 * including the mappings for zodiac signs and color waves.
 */
class GameData {

    /**
     * @var array Mapping of Chinese Zodiac signs to their corresponding numbers.
     */
    public static $zodiacMap = [
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
     * @var array Mapping of colors to their corresponding numbers.
     */
    public static $colorMap = [
        '红波' => [
            'single' => ['01', '07', '13', '19', '23', '29', '35', '45'],
            'double' => ['02', '08', '12', '18', '24', '30', '34', '40', '46'],
        ],
        '蓝波' => [
            'single' => ['03', '09', '15', '25', '31', '37', '41', '47'],
            'double' => ['04', '10', '14', '20', '26', '36', '42', '48'],
        ],
        '绿波' => [
            'single' => ['05', '11', '17', '21', '27', '33', '39', '43', '49'],
            'double' => ['06', '16', '22', '28', '32', '38', '44'],
        ],
    ];
}
