<?php
// File: backend/bot/keyboards.php
// Description: Defines the keyboard layouts for the Telegram bot.

/**
 * Returns the main menu keyboard layout.
 *
 * @return string JSON-encoded keyboard structure.
 */
function get_main_menu_keyboard() {
    $keyboard = [
        'keyboard' => [
            // First row
            [ '⚙️ Settings', '📊 Stats' ],
            // Second row
            [ '🔑 Update Gemini Key', '👤 Manage Users' ],
            // Third row
            [ '✉️ Authorize Email' ]
        ],
        'resize_keyboard' => true, // Make the keyboard smaller
        'one_time_keyboard' => false // Keep the keyboard open
    ];

    return json_encode($keyboard);
}

/**
 * Returns the keyboard for the settings menu.
 *
 * @return string JSON-encoded keyboard structure.
 */
function get_settings_keyboard() {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'View Current Config', 'callback_data' => 'view_config'],
                ['text' => 'Check API Status', 'callback_data' => 'check_api_status']
            ],
            [
                 ['text' => '🔙 Back to Main Menu', 'callback_data' => 'main_menu']
            ]
        ]
    ];
    return json_encode($keyboard);
}

?>
