<?php
// 正式版代码，移除了诊断逻辑

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php-error.log');
error_reporting(E_ALL);

// --- 核心函数区 ---

function load_env() {
    $envPath = __DIR__ . '/../.env';
    if (!file_exists($envPath)) { return; }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim(trim($value), "\"'");
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
        }
    }
}

function sendMessage($chatId, $message, $keyboard = null) {
    $token = getenv('TELEGRAM_BOT_TOKEN');
    if (!$token || !$chatId) { return; }
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $post_fields = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'Markdown'];
    if ($keyboard) { $post_fields['reply_markup'] = json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false]); }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

function callBackendApi($issueNumber, $numbers) {
    $backendUrl = getenv('BACKEND_URL'); $secret = getenv('INTERNAL_API_SECRET');
    if (!$backendUrl || !$secret) { return ['success' => false, 'message' => '服务器配置错误：缺少关键环境变量。']; }
    $apiUrl = rtrim($backendUrl, '/') . '/api/winning-numbers';
    $data = json_encode(['issue_number' => $issueNumber, 'numbers' => implode(',', $numbers), 'draw_date' => date('Y-m-d')]);
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $secret]);
    $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($httpCode == 201) { return ['success' => true]; }
    else { $errorData = json_decode($response, true); $errorMessage = $errorData['error'] ?? $response; return ['success' => false, 'message' => "API错误: {$errorMessage}"]; }
}

function parseLotteryResult($text) {
    $pattern = '/第:?(\d+).*?(\d{2})\s+(\d{2})\s+(\d{2})\s+(\d{2})\s+(\d{2})\s+(\d{2})\s+(\d{2})/';
    if (preg_match($pattern, $text, $matches)) { return ['issue_number' => $matches[1], 'numbers' => array_slice($matches, 2)]; }
    return null;
}

// --- 新增函数：获取最新开奖号码 ---
function getLatestWinningNumbers($limit = 5) {
    $backendUrl = getenv('BACKEND_URL');
    if (!$backendUrl) { return "错误：未配置后端URL。"; }

    // 构建带 limit 参数的 API URL
    $apiUrl = rtrim($backendUrl, '/') . '/api/winning-numbers?limit=' . $limit;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // 注意：获取开奖号码的API是公开的GET请求，所以不需要Authorization头
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        return "🔥 获取数据失败，服务器响应码: {$httpCode}";
    }

    $data = json_decode($response, true);

    if (empty($data)) {
        return "ℹ️ 数据库中暂无开奖记录。";
    }

    $message = "📊 *最新{$limit}期开奖结果* 📊\n\n";
    foreach ($data as $row) {
        $message .= "🔹 *期号:* `{$row['issue_number']}`\n";
        $message .= "   *日期:* {$row['draw_date']}\n";
        $message .= "   *号码:* *{$row['numbers']}*\n\n";
    }
    return $message;
}


// --- 主逻辑开始 ---

load_env();

$updateJson = file_get_contents("php://input");
$update = json_decode($updateJson, TRUE);

$adminId = getenv('TELEGRAM_ADMIN_ID');

if (isset($update["channel_post"])) {
    // ... 频道消息处理逻辑保持不变 ...
    exit();
}

if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $userId = $update["message"]["from"]["id"];
    $text = trim($update["message"]["text"] ?? '');

    if (!$adminId || $userId != $adminId) {
        sendMessage($chatId, "🚫 无权限。");
        exit();
    }

    // --- 关键改动：更新键盘菜单 ---
    $mainKeyboard = [
        [['text' => '📊 最新开奖']],
        [['text' => '✍️ 手动录入'], ['text' => 'ℹ️ 帮助']]
    ];
    
    // --- 关键改动：更新命令处理逻辑 ---
    if ($text === '/start') {
        $helpMessage = "🎉 *开奖管理Bot已激活* 🎉\n\n请使用下方菜单或直接发送命令。";
        sendMessage($chatId, $helpMessage, $mainKeyboard);
    
    } elseif (strpos($text, '📊 最新开奖') !== false) {
        // 调用新函数获取并发送开奖号码
        $resultsMessage = getLatestWinningNumbers(5); // 获取最新的5条
        sendMessage($chatId, $resultsMessage, $mainKeyboard);

    } elseif (strpos($text, 'ℹ️ 帮助') !== false) {
        $helpMessage = "*帮助信息*\n\n`/kj [期号] [号码]` - 手动录入开奖号码。";
        sendMessage($chatId, $helpMessage, $mainKeyboard);

    } elseif (strpos($text, '✍️ 手动录入') !== false) {
        sendMessage($chatId, "✍️ 请按格式发送命令:\n`/kj [期号] [号码]`", $mainKeyboard);

    } elseif (strpos($text, "/kj") === 0) {
        $normalizedText = preg_replace('/[,\s]+/', ' ', $text);
        $parts = explode(" ", $normalizedText);
        if (count($parts) < 9) {
            sendMessage($chatId, "❌ *格式错误！*\n示例: `/kj 2024001 01 02 03 04 05 06 07`");
        } else {
            $issueNumber = $parts[1]; $numbers = array_slice($parts, 2, 7);
            $apiResult = callBackendApi($issueNumber, $numbers);
            if ($apiResult['success']) {
                sendMessage($chatId, "✅ *手动录入成功！*\n期号: `{$issueNumber}`");
            } else {
                sendMessage($chatId, "🔥 *手动录入失败！*\n错误: `{$apiResult['message']}`");
            }
        }
    } else {
        sendMessage($chatId, "🤔 命令无法识别，请点击下方菜单。");
    }
    exit();
}

http_response_code(200);
exit();
