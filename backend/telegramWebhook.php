<?php

// --- Remote Debugging via Telegram ---
// This version sends debug messages to the admin instead of writing to a log file.

// --- Environment Loading ---
// We need to load the env first to get the admin ID and bot token.
$env_loaded = false;
try {
    $envPath = __DIR__ . '/.env';
    if (file_exists($envPath) && is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                putenv(trim($key) . "=" . trim($value, "'\""));
            }
            $env_loaded = true;
        }
    }
} catch (Throwable $e) {
    // Cannot log here yet.
}

// --- Remote Logger Function ---
function super_debug_telegram($message) {
    $adminId = getenv('TELEGRAM_ADMIN_ID');
    $botToken = getenv('TELEGRAM_BOT_TOKEN');

    if (!$adminId || !$botToken) {
        return; // Cannot send debug message without config.
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $payload = [
        'chat_id' => $adminId,
        'text' => "[DEBUG] " . substr($message, 0, 4000), // Keep messages under Telegram's limit
        'disable_web_page_preview' => true,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    // We use @ to suppress errors here as this is a last-ditch debugging effort.
    @curl_exec($ch);
    curl_close($ch);
}

super_debug_telegram("--- SCRIPT_ENTRY --- | Env Loaded: " . ($env_loaded ? 'Yes' : 'No'));

// --- Dependencies ---
try {
    require_once __DIR__ . '/db_operations.php';
    require_once __DIR__ . '/telegram_helpers.php';
    require_once __DIR__ . '/user_state_manager.php';
    // Other helpers are not needed for this specific debug path.
    super_debug_telegram("OK: Dependencies loaded.");
} catch (Throwable $e) {
    super_debug_telegram("FATAL EXCEPTION during require_once: " . $e->getMessage());
    exit;
}

// --- Raw Request Logging ---
$bodyRaw = file_get_contents('php://input');
super_debug_telegram("--- RAW REQUEST --- \n" . $bodyRaw);

// --- Request Processing ---
try {
    $update = json_decode($bodyRaw, true);
    if (!is_array($update)) {
        super_debug_telegram("FATAL: Invalid JSON in request body.");
        http_response_code(200);
        exit;
    }
    super_debug_telegram("OK: JSON decoded.");

    $lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');
    super_debug_telegram("Configured Lottery Channel ID: " . ($lotteryChannelId ?: 'NOT SET'));

    if (isset($update['channel_post'])) {
        super_debug_telegram("Update is a 'channel_post'.");
        $post = $update['channel_post'];
        $chatId = $post['chat']['id'] ?? null;
        $text = trim($post['text'] ?? '');

        super_debug_telegram("Channel Post Chat ID: {$chatId}");
        super_debug_telegram("Channel Post Text: {$text}");

        if (!empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
            super_debug_telegram("MATCH: Channel ID matches. Proceeding to parse.");

            // --- Parsing Logic ---
            // (Re-integrating the full, correct parser)
            $lotteryType = null; $normalizedType = null;
            if (strpos($text, '新澳门六合彩') !== false) { $normalizedType = '新澳门六合彩'; }
            elseif (strpos($text, '香港六合彩') !== false) { $normalizedType = '香港六合彩'; }
            elseif (strpos($text, '老澳') !== false) { $normalizedType = '老澳门六合彩'; }

            if (!$normalizedType) {
                 super_debug_telegram("PARSE_FAIL: Could not determine lottery type.");
            } else {
                super_debug_telegram("PARSE_OK: Type: {$normalizedType}");

                preg_match('/第\s*:?\s*(\d+)\s*期/u', $text, $issue_matches);
                $issue_number = $issue_matches[1] ?? null;

                $lines = explode("\n", $text);
                $winning_numbers = ''; $zodiac_signs = ''; $colors = '';
                foreach ($lines as $line) { if (preg_match('/^[\d\s]+$/', trim($line))) { $winning_numbers = preg_replace('/\s+/', ',', trim($line)); break; } }
                foreach ($lines as $line) { if (preg_match('/^[\x{4e00}-\x{9fa5}\s]+$/u', trim($line)) && !strpos($line, '开奖结果')) { $zodiac_signs = preg_replace('/\s+/', ',', trim($line)); break; } }
                foreach ($lines as $line) { if (strpos($line, '🔵') !== false || strpos($line, '🟢') !== false || strpos($line, '🔴') !== false) { $colors = trim($line); break; } }

                preg_match('/(\d{4}\/\d{1,2}\/\d{1,2})/', $text, $date_matches);
                $drawing_date = isset($date_matches[1]) ? date('Y-m-d', strtotime($date_matches[1])) : date('Y-m-d');

                $number_colors_json = null;
                if (!empty($winning_numbers) && !empty($colors)) {
                    $numbers_arr = explode(',', $winning_numbers);
                    preg_match_all('/(🔵|🟢|🔴)/u', $colors, $color_matches);
                    $colors_arr = $color_matches[0] ?? [];
                    if (count($numbers_arr) === count($colors_arr)) {
                        $color_map = [];
                        $color_name_map = ['🔵' => 'blue', '🟢' => 'green', '🔴' => 'red'];
                        foreach ($numbers_arr as $index => $number) { $color_map[trim($number)] = $color_name_map[$colors_arr[$index]] ?? 'unknown'; }
                        $number_colors_json = json_encode($color_map);
                    }
                }

                super_debug_telegram("PARSED DATA: " . json_encode(compact('normalizedType', 'issue_number', 'winning_numbers', 'zodiac_signs', 'colors', 'drawing_date', 'number_colors_json'), JSON_UNESCAPED_UNICODE));

                if ($issue_number) {
                    $success = storeLotteryResult($normalizedType, $issue_number, $winning_numbers, $zodiac_signs, $colors, $drawing_date, $number_colors_json);
                    super_debug_telegram($success ? "DB_SUCCESS: storeLotteryResult returned true." : "DB_FAIL: storeLotteryResult returned false.");
                } else {
                    super_debug_telegram("PARSE_FAIL: Could not parse issue number.");
                }
            }
        } else {
            super_debug_telegram("NO_MATCH: Channel ID does not match configured ID.");
        }
    } else {
        super_debug_telegram("Update is not a 'channel_post'. Ignoring for lottery processing.");
    }
} catch (Throwable $e) {
    super_debug_telegram("FATAL EXCEPTION during processing: " . $e->getMessage());
}

http_response_code(200);
exit();

?>