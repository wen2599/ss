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
    return 'unknown'; // Should not happen for valid numbers
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
?>