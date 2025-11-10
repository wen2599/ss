<?php
// File: telegram/receiver.php (FINAL PRODUCTION VERSION)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/receiver_debug.log');
error_reporting(E_ALL);

// 立即记录请求
error_log("=== WEBHOOK CALLED ===");
error_log("Time: " . date('Y-m-d H:i:s'));

function read_env_and_get_config() {
    static $config = null;
    if ($config === null) {
        $config = [];
        $envPath = dirname(__DIR__) . '/.env';
        if (file_exists($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, ';') === 0) continue;
                if (strpos($line, ';') !== false) $line = substr($line, 0, strpos($line, ';'));
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $config[trim($name)] = trim(trim($value), "\"'");
                }
            }
        }
    }
    return $config;
}

function get_db_standalone($config) {
    try {
        $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            $config['DB_HOST'] ?? 'localhost',
            $config['DB_PORT'] ?? '3306',
            $config['DB_DATABASE'] ?? ''
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ];
        
        return new PDO($dsn, $config['DB_USERNAME'] ?? '', $config['DB_PASSWORD'] ?? '', $options);
    } catch (PDOException $e) {
        error_log("DATABASE ERROR: " . $e->getMessage());
        throw $e;
    }
}

// --- 优化的自然语言解析器 ---
function parse_natural_lottery_data(string $text) {
    error_log("开始解析自然语言开奖数据");
    
    $lines = explode("\n", trim($text));
    if (count($lines) < 3) {
        error_log("解析失败: 行数不足");
        return null;
    }
    
    $lotteryType = '';
    $issueNumber = '';
    $winningNumbers = [];
    $zodiacs = [];
    $colors = [];
    
    // 解析第一行：彩票类型和期号
    $firstLine = trim($lines[0]);
    if (strpos($firstLine, '新澳门六合彩') !== false) {
        $lotteryType = '新澳门六合彩';
    } elseif (strpos($firstLine, '香港六合彩') !== false) {
        $lotteryType = '香港六合彩';
    } elseif (strpos($firstLine, '老澳') !== false) {
        $lotteryType = '老澳门六合彩';
    } else {
        error_log("解析失败: 无法识别彩票类型");
        return null;
    }
    
    // 提取期号
    if (!preg_match('/第:?(\d+)/', $firstLine, $matches)) {
        error_log("解析失败: 无法提取期号");
        return null;
    }
    $issueNumber = $matches[1];
    
    // 解析第二行：开奖号码
    $numbersLine = trim($lines[1]);
    preg_match_all('/\b\d+\b/', $numbersLine, $numberMatches);
    $winningNumbers = $numberMatches[0] ?? [];
    
    if (count($winningNumbers) < 6) {
        error_log("解析失败: 号码数量不足 - " . count($winningNumbers));
        return null;
    }
    
    // 解析第三行：生肖
    if (isset($lines[2])) {
        $zodiacLine = trim($lines[2]);
        $zodiacMap = [
            '鼠' => '鼠', '牛' => '牛', '虎' => '虎', '兔' => '兔',
            '龍' => '龙', '龙' => '龙', '蛇' => '蛇', '馬' => '马',
            '马' => '马', '羊' => '羊', '猴' => '猴', '雞' => '鸡',
            '鸡' => '鸡', '狗' => '狗', '豬' => '猪', '猪' => '猪'
        ];
        
        $zodiacParts = preg_split('/\s+/', $zodiacLine);
        foreach ($zodiacParts as $part) {
            $part = trim($part);
            if (!empty($part) && isset($zodiacMap[$part])) {
                $zodiacs[] = $zodiacMap[$part];
            }
        }
    }
    
    // 解析第四行：波色
    if (isset($lines[3])) {
        $colorLine = trim($lines[3]);
        $colorMap = [
            '🔴' => '红波', '🟢' => '绿波', '🔵' => '蓝波'
        ];
        
        $colorParts = preg_split('/\s+/', $colorLine);
        foreach ($colorParts as $part) {
            foreach ($colorMap as $emoji => $color) {
                if (strpos($part, $emoji) !== false) {
                    $colors[] = $color;
                    break;
                }
            }
        }
    }
    
    // 确保数组长度匹配
    if (count($zodiacs) !== count($winningNumbers)) {
        $zodiacs = array_fill(0, count($winningNumbers), '未知');
    }
    
    if (count($colors) !== count($winningNumbers)) {
        $colors = array_fill(0, count($winningNumbers), '未知');
    }
    
    error_log("解析成功: {$lotteryType} 期号:{$issueNumber} 号码:" . implode(',', $winningNumbers));
    
    return [
        'lottery_type' => $lotteryType,
        'issue_number' => $issueNumber,
        'winning_numbers' => $winningNumbers,
        'zodiac_signs' => $zodiacs,
        'colors' => $colors,
        'drawing_date' => date('Y-m-d')
    ];
}

// ASCII 解析器（保留兼容性）
function parse_ascii_lottery_data(string $text) {
    $text = trim($text, "` \n\r\t\v\x00");
    
    if (strpos($text, 'lottery_result|') !== 0) {
        return null;
    }

    $parts = explode('|', $text);
    array_shift($parts);
    $data = [];
    
    foreach ($parts as $part) {
        if (strpos($part, ':') !== false) {
            list($key, $value) = explode(':', $part, 2);
            $data[$key] = $value;
        }
    }
    
    if (!isset($data['type']) || !isset($data['issue']) || !isset($data['nums'])) {
        return null;
    }

    $type_map = ['1' => '香港六合彩', '2' => '新澳门六合彩', '3' => '老澳门六合彩'];
    $color_map_char = ['R' => '红波', 'G' => '绿波', 'B' => '蓝波'];
    $zodiac_map_char = [
        'S' => '鼠', 'N' => '牛', 'H' => '虎', 'T' => '兔', 'L' => '龙', 's' => '蛇',
        'M' => '马', 'Y' => '羊', 'h' => '猴', 'J' => '鸡', 'G' => '狗', 'Z' => '猪'
    ];
    
    $winning_numbers = explode(',', $data['nums']);
    $zodiacs_chars = isset($data['zodiacs']) ? explode(',', $data['zodiacs']) : [];
    $colors_chars = isset($data['colors']) ? explode(',', $data['colors']) : [];
    
    $colors = [];
    foreach ($colors_chars as $char) {
        $colors[] = $color_map_char[$char] ?? '未知';
    }

    $zodiacs = [];
    foreach ($zodiacs_chars as $char) {
        $zodiacs[] = $zodiac_map_char[$char] ?? '未知';
    }

    return [
        'lottery_type' => $type_map[$data['type']] ?? '未知类型',
        'issue_number' => $data['issue'],
        'winning_numbers' => $winning_numbers,
        'zodiac_signs' => $zodiacs,
        'colors' => $colors,
        'drawing_date' => date('Y-m-d')
    ];
}

function sendTelegramMessage($chatId, $text, $config) {
    $botToken = $config['TELEGRAM_BOT_TOKEN'] ?? '';
    if (!$botToken) return;
    
    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];
    
    $context = stream_context_create($options);
    @file_get_contents($apiUrl, false, $context);
}

// 主逻辑开始
try {
    $env_config = read_env_and_get_config();
    
    // 安全验证
    $secret_from_env = $env_config['TELEGRAM_WEBHOOK_SECRET'] ?? null;
    $secret_from_get = $_GET['secret'] ?? null;
    
    if (!$secret_from_env || $secret_from_get !== $secret_from_env) {
        http_response_code(403);
        exit('Forbidden');
    }

    $input = file_get_contents('php://input');
    if (!$input) {
        http_response_code(200);
        echo "OK";
        exit;
    }
    
    $update = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(200);
        echo "OK";
        exit;
    }
    
    // 处理频道消息
    if (isset($update['channel_post']['text'])) {
        $message_text = $update['channel_post']['text'];
        
        // 首先尝试自然语言解析
        $parsedData = parse_natural_lottery_data($message_text);
        
        // 如果失败，尝试ASCII解析
        if (!$parsedData) {
            $parsedData = parse_ascii_lottery_data($message_text);
        }
        
        if ($parsedData) {
            error_log("解析成功: {$parsedData['lottery_type']} 期号:{$parsedData['issue_number']}");
            
            try {
                $pdo = get_db_standalone($env_config);
                $sql = "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date)
                        VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE
                        winning_numbers=VALUES(winning_numbers), zodiac_signs=VALUES(zodiac_signs),
                        colors=VALUES(colors), drawing_date=VALUES(drawing_date),
                        created_at=CURRENT_TIMESTAMP";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $parsedData['lottery_type'],
                    $parsedData['issue_number'],
                    json_encode($parsedData['winning_numbers']),
                    json_encode($parsedData['zodiac_signs']),
                    json_encode($parsedData['colors']),
                    $parsedData['drawing_date']
                ]);
                
                if ($result) {
                    error_log("数据库插入成功: {$parsedData['issue_number']}");
                    
                    // 发送确认消息给管理员
                    $adminId = $env_config['TELEGRAM_ADMIN_ID'] ?? null;
                    if ($adminId) {
                        $confirmMsg = "✅ 开奖数据已保存\n" .
                                     "📊 类型: " . $parsedData['lottery_type'] . "\n" .
                                     "🎫 期号: " . $parsedData['issue_number'] . "\n" .
                                     "🔢 号码: " . implode(', ', $parsedData['winning_numbers']);
                        sendTelegramMessage($adminId, $confirmMsg, $env_config);
                    }
                }
                
            } catch (PDOException $e) {
                error_log("数据库错误: " . $e->getMessage());
            }
        }
    }
    // 处理私聊消息
    elseif (isset($update['message']['text'])) {
        $chatId = $update['message']['chat']['id'];
        $userId = $update['message']['from']['id'];
        $text = $update['message']['text'];
        
        $adminId = (int)($env_config['TELEGRAM_ADMIN_ID'] ?? 0);
        
        if ($userId === $adminId) {
            if ($text === '/start') {
                sendTelegramMessage($chatId, "👋 欢迎回来，管理员！系统运行正常。", $env_config);
            } elseif ($text === '/status') {
                sendTelegramMessage($chatId, "✅ 系统状态正常\n🕒 最后检查: " . date('Y-m-d H:i:s'), $env_config);
            }
        }
    }
    
    http_response_code(200);
    echo "OK";

} catch (Throwable $e) {
    error_log("严重错误: " . $e->getMessage());
    http_response_code(200);
    echo "OK";
}
?>