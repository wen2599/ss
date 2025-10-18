<?php
// test_parser.php (CORRECTED AND COMPLETE FINAL VERSION)

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "--- Starting Parser Test (Corrected Version) ---\n\n";

// 包含我们刚刚修复好的 db_operations.php
// 它现在会自动处理 .env 文件的加载
require_once __DIR__ . '/db_operations.php';

// --- 我们将解析函数也包含在这个文件里，确保它一定存在 ---
function write_test_log($msg) {
    echo "LOG: " . htmlspecialchars($msg) . "\n";
}

function parse_lottery_data($text) {
    $data = [
        'lottery_type'    => null, 'issue_number'    => null, 'winning_numbers' => [],
        'zodiac_signs'    => [],   'colors'          => [],   'drawing_date'    => date('Y-m-d')
    ];
    if (preg_match('/(新澳门六合彩|香港六合彩|老澳.*?)第:(\d+)期/', $text, $h)) {
        $data['lottery_type'] = (strpos($h[1], '老澳') !== false) ? '老澳门六合彩' : trim($h[1]);
        $data['issue_number'] = $h[2];
    } else { write_test_log("[Parser] Failed: Header match."); return null; }
    
    $lines = array_values(array_filter(array_map('trim', explode("\n", trim($text))), function($line) { return !empty($line); }));
    
    if (count($lines) < 4) { write_test_log("[Parser] Failed: Not enough lines."); return null; }
    
    $data['winning_numbers'] = preg_split('/\s+/', $lines[1]);
    $data['zodiac_signs']    = preg_split('/\s+/', $lines[2]);
    $data['colors']          = preg_split('/\s+/', $lines[3]);
    
    $num_count = count($data['winning_numbers']);
    if ($num_count === 0 || $num_count !== count($data['zodiac_signs']) || $num_count !== count($data['colors'])) {
        write_test_log("[Parser] Failed: Mismatch in data counts.");
        return null;
    }
    
    write_test_log("[Parser] Success: Parsed issue {$data['issue_number']} for {$data['lottery_type']}");
    return $data;
}
// --- 函数定义结束 ---


// 模拟从频道收到的原始消息文本
$test_message_text = "新澳门六合彩第:2025288期开奖结果:\n01  02  03  04  05  06  07\n鼠  牛  虎  兔  龙  蛇  马\n🔴 🔴 🔵 🔵 🟢 🟢 🔴";

echo "1. Parsing message...\n";
$parsedData = parse_lottery_data($test_message_text);

if ($parsedData) {
    echo "--- PARSING SUCCESS! ---\n";
    print_r($parsedData);
    echo "\n";
    
    echo "2. Preparing and storing data...\n";
    
    try {
        // 先手动清空表，确保测试结果清晰
        $pdo = get_db_connection();
        if ($pdo && !is_array($pdo)) {
            $pdo->exec("DELETE FROM lottery_results");
            echo "   - (Cleared lottery_results table)\n";
        }

        // *** 这是修正后的、参数完全正确的函数调用 ***
        $success = storeLotteryResult(
            $parsedData['lottery_type'],
            $parsedData['issue_number'],
            json_encode($parsedData['winning_numbers']),
            json_encode($parsedData['zodiac_signs']),
            json_encode($parsedData['colors']),
            $parsedData['drawing_date']
        );
        
        if ($success) {
            echo "\n--- RESULT: SUCCESS! Data stored in database! Please check your website now. ---\n";
        } else {
            echo "\n--- RESULT: FAILURE! storeLotteryResult() returned false. Check your PHP error log for the reason. ---\n";
        }
        
    } catch (Exception $e) {
        echo "\n--- RESULT: FAILURE! An exception occurred during DB operation: " . $e->getMessage() . " ---\n";
    }
    
} else {
    echo "--- RESULT: FAILURE! Parsing failed. Check logs above. ---\n";
}

echo "\n--- Test Finished ---\n";
echo "</pre>";

?>
