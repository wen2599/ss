<?php
// backend/handlers.php

require_once __DIR__ . '/bootstrap.php';

/**
 * Main handler for all incoming Telegram updates.
 *
 * @param array $update The decoded JSON update from Telegram.
 */
function handle_telegram_update($update) {
    // We are only interested in new channel posts.
    if (isset($update['channel_post']['text'])) {
        $text = $update['channel_post']['text'];

        // Let's assume a generic lottery type for now, or try to parse it.
        // For simplicity, we will hardcode 'xglhc' (香港六合彩).
        // A more advanced version could parse "香港/澳门" from the text.
        $lottery_type = 'xglhc';

        parse_and_save_lottery_draw($lottery_type, $text);
    }
}

/**
 * Parses a text message to extract lottery draw information and saves it to the database.
 *
 * @param string $lottery_type The type of lottery (e.g., 'xglhc').
 * @param string $text The message text from the channel post.
 */
function parse_and_save_lottery_draw($lottery_type, $text) {
    // Regex to capture issue number, date, and the numbers.
    // This regex is designed to be flexible and capture the key parts.
    $pattern = '/第\s*(\d+)\s*期\s*.*?(\d{2}\/\d{2}\/\d{4}).*?号码\)\s*([\d,\s]+?)\s*\(特别号码\)\s*([\d,\s]+)/s';

    if (preg_match($pattern, $text, $matches)) {
        $issue_number = trim($matches[1]);
        $date_str = trim($matches[2]);
        $regular_numbers = trim($matches[3]);
        $special_number = trim($matches[4]);

        // Combine all numbers into a single string for storage.
        // We can format it nicely here.
        $all_numbers = str_replace("\n", " ", $regular_numbers) . " + " . $special_number;
        // Clean up any extra commas or spaces
        $all_numbers = trim(str_replace([' ', ','], ' ', $all_numbers));
        $all_numbers = preg_replace('/\s+/', ' ', $all_numbers);


        // Convert date from DD/MM/YYYY to YYYY-MM-DD for database storage.
        try {
            $draw_date = DateTime::createFromFormat('d/m/Y', $date_str)->format('Y-m-d');
        } catch (Exception $e) {
            write_log("Failed to parse date: $date_str");
            return; // Stop processing if the date is invalid.
        }

        // Now, save the data to the database.
        try {
            $pdo = get_db_connection();

            // First, check if this issue number already exists to prevent duplicates.
            $stmt_check = $pdo->prepare("SELECT id FROM lottery_draws WHERE lottery_type = ? AND issue_number = ?");
            $stmt_check->execute([$lottery_type, $issue_number]);
            if ($stmt_check->fetch()) {
                write_log("Duplicate entry skipped: Type=$lottery_type, Issue=$issue_number");
                return;
            }

            // Insert the new record.
            $stmt = $pdo->prepare(
                "INSERT INTO lottery_draws (lottery_type, issue_number, draw_date, numbers) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$lottery_type, $issue_number, $draw_date, $all_numbers]);
            write_log("Successfully saved draw: Type=$lottery_type, Issue=$issue_number");

        } catch (PDOException $e) {
            write_log("Database error: " . $e->getMessage());
            // It's important to log but not re-throw, as the bot.php will handle the final response.
        }

    } else {
        write_log("Failed to parse lottery draw text: " . $text);
    }
}
