<?php

// --- Super-Debug Logging ---
// This is the very first thing in the script.
// We try two locations to maximize the chance of getting a log file.
function super_debug($message) {
    $timestamp = date('[Y-m-d H:i:s]');
    $log_message = "{$timestamp} {$message}\n";

    // Try system temp directory
    @file_put_contents(sys_get_temp_dir() . '/final_debug.log', $log_message, FILE_APPEND);

    // Try local directory
    @file_put_contents(__DIR__ . '/final_debug.log', $log_message, FILE_APPEND);
}

// Log script entry immediately.
super_debug("--- SCRIPT_ENTRY ---");

// --- Environment Loading ---
try {
    super_debug("Loading environment variables...");
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath) || !is_readable($envPath)) {
        super_debug("FATAL: .env file not found or not readable at {$envPath}");
        // We don't exit, to see if we can log the incoming request anyway.
    } else {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, "'\"");
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
            super_debug("OK: Environment variables loaded.");
        } else {
            super_debug("WARNING: Failed to read .env file content.");
        }
    }
} catch (Throwable $e) {
    super_debug("EXCEPTION during env load: " . $e->getMessage());
}

// --- Dependencies ---
try {
    super_debug("Loading dependencies...");
    require_once __DIR__ . '/db_operations.php';
    require_once __DIR__ . '/telegram_helpers.php';
    require_once __DIR__ . '/user_state_manager.php';
    require_once __DIR__ . '/env_manager.php';
    require_once __DIR__ . '/api_curl_helper.php';
    require_once __DIR__ . '/gemini_ai_helper.php';
    require_once __DIR__ . '/cloudflare_ai_helper.php';
    super_debug("OK: Dependencies loaded.");
} catch (Throwable $e) {
    super_debug("FATAL EXCEPTION during require_once: " . $e->getMessage());
    exit;
}

// --- Raw Request Logging ---
super_debug("--- RAW_REQUEST_START ---");
super_debug("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
$bodyRaw = file_get_contents('php://input');
super_debug("Raw Body: " . $bodyRaw);
super_debug("--- RAW_REQUEST_END ---");

// --- Request Processing ---
try {
    $update = json_decode($bodyRaw, true);
    if (!is_array($update)) {
        super_debug("FATAL: Invalid JSON in request body.");
        http_response_code(200);
        exit;
    }
    super_debug("OK: JSON decoded successfully.");

    $lotteryChannelId = getenv('LOTTERY_CHANNEL_ID');
    super_debug("Configured Lottery Channel ID: " . ($lotteryChannelId ?: 'NOT SET'));

    // The critical check: Is this a channel post?
    if (isset($update['channel_post'])) {
        super_debug("Update is a 'channel_post'.");
        $post = $update['channel_post'];
        $chatId = $post['chat']['id'] ?? null;
        $text = trim($post['text'] ?? '');

        super_debug("Channel Post Chat ID: {$chatId}");
        super_debug("Channel Post Text: {$text}");

        if (!empty($lotteryChannelId) && (string)$chatId === (string)$lotteryChannelId) {
            super_debug("MATCH: Channel ID matches configured ID. Proceeding to parse.");

            // --- Parsing Logic ---
            $lotteryType = null;
            if (strpos($text, '新澳门六合彩') !== false) $lotteryType = '新澳门六合彩';
            elseif (strpos($text, '香港六合彩') !== false) $lotteryType = '香港六合彩';
            elseif (strpos($text, '老澳') !== false) $lotteryType = '老澳门六合彩';

            if (!$lotteryType) {
                super_debug("PARSE_FAIL: Could not determine lottery type.");
            } else {
                super_debug("PARSE_OK: Determined lottery type: {$lotteryType}");

                preg_match('/第\s*:?\s*(\d+)\s*期/u', $text, $issue_matches);
                $issue_number = $issue_matches[1] ?? null;
                super_debug("Parsed Issue Number: {$issue_number}");

                // ... (rest of parsing logic) ...

                if ($issue_number) {
                    // ... (call to storeLotteryResult) ...
                    $success = storeLotteryResult($lotteryType, $issue_number, '...','...','...','...','...'); // Simplified for brevity
                    if($success) {
                        super_debug("DB_SUCCESS: storeLotteryResult returned true.");
                    } else {
                        super_debug("DB_FAIL: storeLotteryResult returned false.");
                    }
                } else {
                    super_debug("PARSE_FAIL: Could not parse issue number.");
                }
            }
        } else {
            super_debug("NO_MATCH: Channel ID does not match configured ID.");
        }
    } else {
        super_debug("Update is not a 'channel_post'. Ignoring for lottery processing.");
    }
} catch (Throwable $e) {
    super_debug("FATAL EXCEPTION during request processing: " . $e->getMessage());
}

super_debug("--- SCRIPT_END ---");
http_response_code(200);
echo json_encode(['status' => 'ok']);
exit();

?>