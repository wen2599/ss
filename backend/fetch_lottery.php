<?php
// backend/fetch_lottery.php
// This script should be run periodically via a Cron Job.

require_once __DIR__ . '/env_loader.php';
require_once __DIR__ . '/db_connection.php';

// --- Main Execution ---

// Fetch new updates from Telegram
$updates = get_telegram_updates();
if (empty($updates)) {
    echo "No new messages.\n";
    exit;
}

// Get a database connection
$db_conn = get_db_connection();
if (!$db_conn) {
    // Error is logged within get_db_connection
    exit;
}
$db_conn->set_charset("utf8mb4");

// Prepare the SQL statement for inserting/updating lottery results
$stmt = $db_conn->prepare(
    "INSERT INTO lottery_results (issue_number, numbers, draw_date) 
     VALUES (?, ?, ?) 
     ON DUPLICATE KEY UPDATE numbers=VALUES(numbers), draw_date=VALUES(draw_date)"
);

if (!$stmt) {
    error_log("Failed to prepare statement: " . $db_conn->error);
    exit;
}

$last_update_id = 0;

foreach ($updates as $update) {
    if (isset($update['channel_post']['text'])) {
        $text = $update['channel_post']['text'];
        
        $result = parse_lottery_message($text);
        
        if ($result) {
            $stmt->bind_param("sss", $result['issue_number'], $result['numbers'], $result['draw_date']);
            if (!$stmt->execute()) {
                error_log("Failed to execute statement for issue {$result['issue_number']}: " . $stmt->error);
            } else {
                echo "Successfully processed issue: {$result['issue_number']}\n";
            }
        }
    }
    // Track the last update ID to save offset later
    $last_update_id = $update['update_id'];
}

$stmt->close();
$db_conn->close();

// Save the new offset to prevent reprocessing messages
if ($last_update_id > 0) {
    save_telegram_offset($last_update_id + 1);
    echo "Processed up to update_id: " . $last_update_id . "\n";
}


// --- Helper Functions ---

/**
 * Fetches new updates from the Telegram Bot API.
 * @return array The list of updates.
 */
function get_telegram_updates() {
    $bot_token = getenv('TELEGRAM_BOT_TOKEN');
    if (!$bot_token) {
        error_log("Error: TELEGRAM_BOT_TOKEN is not set.");
        return [];
    }

    $offset_file = __DIR__ . '/telegram_offset.txt';
    $offset = file_exists($offset_file) ? (int)file_get_contents($offset_file) : 0;

    $api_url = "https://api.telegram.org/bot{$bot_token}/getUpdates?offset={$offset}&timeout=60&allowed_updates=[\"channel_post\"]";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return [];
    }
    
    curl_close($ch);

    $data = json_decode($response, true);

    if (!$data || !$data['ok'] || empty($data['result'])) {
        return [];
    }

    return $data['result'];
}

/**
 * Parses the text of a channel message to extract lottery data.
 * @param string $text The message text.
 * @return array|null The parsed data or null if it doesn't match.
 */
function parse_lottery_message($text) {
    // Regex to capture issue number and the line with lottery numbers
    $pattern = '/第:?\s*(\d+)\s*期开奖结果:\s*\n([\d\s]+)/';

    if (preg_match($pattern, $text, $matches)) {
        $issue_number = $matches[1];
        $numbers_line = trim($matches[2]);
        
        // Split the numbers line by any whitespace
        $numbers_array = preg_split('/\s+/', $numbers_line);
        
        // We expect exactly 7 numbers
        if (count($numbers_array) === 7) {
            $special_number = array_pop($numbers_array);
            $regular_numbers = implode(',', $numbers_array);
            $formatted_numbers = $regular_numbers . '+' . $special_number;
            
            // Try to extract the date from the timestamp, e.g., [2025/10/28 21:34]
            $draw_date = date('Y-m-d'); // Default to today
            if (preg_match('/\[(\d{4}\/\d{2}\/\d{2})/', $text, $date_matches)) {
                $draw_date = str_replace('/', '-', $date_matches[1]);
            }

            return [
                'issue_number' => $issue_number,
                'numbers' => $formatted_numbers,
                'draw_date' => $draw_date
            ];
        }
    }
    
    return null; // Return null if parsing fails
}

/**
 * Saves the last processed update ID.
 * @param int $offset The next update ID to fetch.
 */
function save_telegram_offset($offset) {
    $offset_file = __DIR__ . '/telegram_offset.txt';
    file_put_contents($offset_file, $offset);
}
