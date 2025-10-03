<?php
/**
 * Defines the namespace for the application's library classes.
 */
namespace App;

/**
 * Class GameData
 *
 * Provides static data related to the lottery games, such as color mappings.
 * This centralizes game-specific information.
 */
class GameData {

    /**
     * @var array A map defining which numbers belong to which color group.
     * The structure includes single and double digit numbers for each color.
     */
    public static array $colorMap = [
        "红波" => [
            "single" => ["01", "02", "07", "08", "12", "13", "18", "19", "23", "24", "29", "30", "34", "35", "40", "45", "46"],
            "double" => []
        ],
        "蓝波" => [
            "single" => ["03", "04", "09", "10", "14", "15", "20", "25", "26", "31", "36", "37", "41", "42", "47", "48"],
            "double" => []
        ],
        "绿波" => [
            "single" => ["05", "06", "11", "16", "17", "21", "22", "27", "28", "32", "33", "38", "39", "43", "44", "49"],
            "double" => []
        ]
    ];

    // In the future, other static game data like zodiac signs could be added here.
}
?>