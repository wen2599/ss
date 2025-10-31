<?php
// backend/bot/lottery_processor.php

require_once __DIR__ . '/../db_connector.php';

/**
 * Parses lottery result text from a channel post and stores it in the database.
 *
 * @param string $text The text content from the Telegram channel post.
 * @return bool True on success, false on failure.
 */
function process_lottery_result($text) {
    $patterns = [
        '新澳门六合彩' => '/(新澳门六合彩)第:(\d{7,8})期开奖结果:\n([\d\s]+)/u',
        '香港六合彩'   => '/(香港六合彩)第:(\d{7,8})期开奖结果:\n([\d\s]+)/u',
        '老澳21.30'  => '/(老澳21\.30)第:(\d{7,8})期开奖结果:\n([\d\s]+)/u',
    ];

    $lottery_type = null;
    $issue_number = null;
    $numbers_raw = null;

    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $lottery_type = trim($matches[1]);
            $issue_number = trim($matches[2]);
            $numbers_raw = trim($matches[3]);
            break;
        }
    }

    // If no pattern matched, it's not a lottery result we are interested in.
    if (!$lottery_type) {
        error_log("Lottery Processor: Text did not match any known lottery pattern.");
        return false;
    }

    // Sanitize and format the lottery numbers
    $numbers = preg_replace('/\s+/s', ' ', $numbers_raw); // Replace multiple spaces/newlines with a single space
    $numbers_list = explode(' ', $numbers); // Split into an array
    $numbers_list = array_filter($numbers_list); // Remove any empty elements
    if(count($numbers_list) !== 7) { // Validate that we have exactly 7 numbers
        error_log("Lottery Processor: Failed to parse exactly 7 numbers for issue {$issue_number}. Found: " . $numbers);
        return false;
    }
    $formatted_numbers = implode(',', $numbers_list);

    // Now, let's save this to the database
    $pdo = get_db_connection();
    if (!$pdo) {
        error_log("Lottery Processor: Failed to get database connection.");
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO lottery_results (lottery_type, issue_number, numbers, created_at) " . 
            "VALUES (:lottery_type, :issue_number, :numbers, NOW()) " . 
            "ON DUPLICATE KEY UPDATE numbers = :numbers, created_at = NOW()"
        );

        $stmt->execute([
            ':lottery_type' => $lottery_type,
            ':issue_number' => $issue_number,
            ':numbers' => $formatted_numbers
        ]);
        
        error_log("Lottery Processor: Successfully inserted/updated issue {$issue_number} for {$lottery_type}.");
        return true;

    } catch (PDOException $e) {
        error_log("Lottery Processor: Database error - " . $e->getMessage());
        return false;
    }
}
