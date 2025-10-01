<?php

namespace App\\Lib;

/**
 * Class GameData
 *
 * This class holds the static data for the Mark Six lottery game logic,
 * including the mappings for zodiac signs and color waves, and provides
 * utility methods to access this data.
 */
class GameData {

    /**
     * @var array Mapping of Chinese Zodiac signs to their corresponding numbers.
     * Note: This is a simplified, static mapping. In a real scenario, this might
     * need to be dynamically calculated based on the year.
     */
    public static array $zodiacMap = [
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
    public static array $colorMap = [
        '红' => ['01', '02', '07', '08', '12', '13', '18', '19', '23', '24', '29', '30', '34', '35', '40', '45', '46'],
        '蓝' => ['03', '04', '09', '10', '14', '15', '20', '25', '26', '31', '36', '37', '41', '42', '47', '48'],
        '绿' => ['05', '06', '11', '16', '17', '21', '22', '27', '28', '32', '33', '38', '39', '43', '44', '49'],
    ];

    /**
     * Get the zodiac sign for a given number.
     * The number should be a zero-padded string (e.g., '01', '49').
     */
    public static function getZodiacByNumber(string $number): ?string
    {
        foreach (self::$zodiacMap as $zodiac => $numbers) {
            if (in_array($number, $numbers)) {
                return $zodiac;
            }
        }
        return null;
    }

    /**
     * Get the color for a given number.
     * The number should be a zero-padded string (e.g., '01', '49').
     */
    public static function getColorByNumber(string $number): ?string
    {
        foreach (self::$colorMap as $color => $numbers) {
            if (in_array($number, $numbers)) {
                return $color;
            }
        }
        return null;
    }
    
    /**
     * Get all numbers associated with a specific zodiac sign.
     */
    public static function getNumbersByZodiac(string $zodiac): array
    {
        return self::$zodiacMap[$zodiac] ?? [];
    }
    
    /**
     * Get all numbers associated with a specific color.
     */
    public static function getNumbersByColor(string $color): array
    {
        return self::$colorMap[$color] ?? [];
    }
}
