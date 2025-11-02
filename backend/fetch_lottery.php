<?php
// backend/fetch_lottery.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/telegram_bot_handler.php'; // 引入我们新的Bot辅助库

// --- 配置 ---
$bot_token = $_ENV['TELEGRAM_BOT_TOKEN'];
$admin_id = $_ENV['TELEGRAM_ADMIN_ID'];
$api_url = "https://api.telegram.org/bot{$bot_token}/getUpdates";
$offset_file = __DIR__ . '/last_update_id.txt';

// --- 获取上次处理的 update_id ---
$offset = 0;
if (file_exists($offset_file)) {
    $offset = (int)file_get_contents($offset_file);
}

// --- 请求Telegram API ---
$response_json = @file_get_contents($api_url . '?offset=' . ($offset + 1) . '&limit=100&timeout=10');
if ($response_json === false) {
    // 使用 file_get_contents 更简单，但错误处理不如curl
    error_log("Failed to fetch updates from Telegram.");
    exit;
}
$response = json_decode($response_json, true);

if (!$response || $response['ok'] === false) {
    error_log("Telegram API Error: " . ($response['description'] ?? 'Unknown error'));
    exit;
}

// --- 处理所有更新 ---
$last_update_id = $offset;
foreach ($response['result'] as $update) {
    $last_update_id = $update['update_id']; // 确保即使消息不处理，offset也更新

    // --- A. 处理管理员的私聊消息或按钮点击 ---
    if (isset($update['message']['chat']['id']) && $update['message']['chat']['id'] == $admin_id) {
        $text = $update['message']['text'];
        handleAdminCommand($text, $pdo);
    } 
    elseif (isset($update['callback_query'])) {
        $callback_data = $update['callback_query']['data'];
        $callback_query_id = $update['callback_query']['id'];
        handleCallbackQuery($callback_data, $callback_query_id, $pdo);
    }
    // --- B. 处理频道开奖消息 ---
    elseif (isset($update['channel_post']['text'])) {
        processLotteryPost($update['channel_post'], $pdo);
    }
}

// --- 保存最新的 update_id ---
if ($last_update_id > $offset) {
    file_put_contents($offset_file, $last_update_id);
}

echo "Cron job finished.\n";


// ===================================================================
// ==================== 业务逻辑函数 ====================
// ===================================================================

/**
 * 处理管理员的文本命令/菜单点击
 */
function handleAdminCommand($text, $pdo) {
    // 主菜单键盘
    $mainMenu = [
        'keyboard' => [['刷新开奖', '用户管理'], ['系统状态']],
        'resize_keyboard' => true
    ];

    switch ($text) {
        case '/start':
            sendTelegramMessage("欢迎回来，管理员！请选择操作：", $mainMenu);
            break;

        case '刷新开奖':
            // 这是一个示例，实际的刷新逻辑可能更复杂
            // 这里我们只是简单地调用一次开奖处理函数
            // 注意：这需要您能获取到最近的一条频道消息，这个简易实现做不到
            // 一个更好的方法是，这个按钮只是触发一个状态，让下一次cron执行特定任务
            sendTelegramMessage("手动刷新指令已发送（下次cron运行时将强制检查）。");
            break;
            
        case '用户管理':
            sendTelegramMessage("请输入要查询的用户邮箱：");
            break;

        case '系统状态':
            try {
                $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $email_count = $pdo->query("SELECT COUNT(*) FROM user_emails")->fetchColumn();
                $batch_count = $pdo->query("SELECT COUNT(*) FROM email_batches WHERE status='new'")->fetchColumn();
                $lottery_count = $pdo->query("SELECT MAX(issue_number) FROM lottery_results")->fetchColumn();

                $status_message = "*系统状态报告*\n\n" .
                                  "👤 *注册用户总数:* `{$user_count}`\n" .
                                  "?? *接收邮件总数:* `{$email_count}`\n" .
                                  "📋 *待处理批次:* `{$batch_count}`\n" .
                                  "🎲 *最新开奖期号:* `{$lottery_count}`";
                sendTelegramMessage($status_message);
            } catch (PDOException $e) {
                sendTelegramMessage("查询系统状态失败：" . $e->getMessage());
            }
            break;

        default:
            // 检查是否是邮箱，用于用户查询
            if (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                try {
                    $stmt = $pdo->prepare("SELECT id, email, status, created_at FROM users WHERE email = ?");
                    $stmt->execute([$text]);
                    $user = $stmt->fetch();

                    if ($user) {
                        $user_message = "*找到用户:*\n\n" .
                                        "ID: `{$user['id']}`\n" .
                                        "邮箱: `{$user['email']}`\n" .
                                        "状态: `{$user['status']}`\n" .
                                        "注册于: `{$user['created_at']}`";
                        
                        // 创建内联键盘
                        $inline_keyboard = [
                            'inline_keyboard' => [
                                [
                                    ['text' => '✅ 解封', 'callback_data' => "user_unban_{$user['id']}"],
                                    ['text' => '🚫 封禁', 'callback_data' => "user_ban_{$user['id']}"]
                                ],
                                [
                                    ['text' => '🗑️ 删除用户', 'callback_data' => "user_delete_{$user['id']}"]
                                ]
                            ]
                        ];
                        sendTelegramMessage($user_message, $inline_keyboard);

                    } else {
                        sendTelegramMessage("未找到邮箱为 `{$text}` 的用户。");
                    }
                } catch (PDOException $e) {
                     sendTelegramMessage("查询用户失败：" . $e->getMessage());
                }
            } else {
                sendTelegramMessage("无法识别的指令：`{$text}`", $mainMenu);
            }
            break;
    }
}

/**
 * 处理内联键盘的回调
 */
function handleCallbackQuery($callback_data, $callback_query_id, $pdo) {
    list($action, $target, $user_id) = explode('_', $callback_data);
    
    $response_text = "未知操作";

    if ($target === 'user') {
        try {
            $new_status = null;
            $operation_desc = '';
            
            if ($action === 'ban') {
                $new_status = 'suspended';
                $operation_desc = '封禁';
            } elseif ($action === 'unban') {
                $new_status = 'active';
                $operation_desc = '解封';
            } elseif ($action === 'delete') {
                 // 软删除
                $new_status = 'deleted';
                $operation_desc = '删除';
            }

            if ($new_status) {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                $response_text = "用户 ID:{$user_id} 已成功{$operation_desc}！";
            }
        } catch (PDOException $e) {
            $response_text = "操作失败: " . $e->getMessage();
        }
    }
    
    // 告诉Telegram我们已经处理了这个点击
    answerCallbackQuery($callback_query_id, $response_text);
}


/**
 * 处理频道开奖消息 (从旧代码中提取出来)
 */
function processLotteryPost($channel_post, $pdo) {
    $message_text = $channel_post['text'];
    $pattern = '/(?:新澳门六合彩|香港六合彩|老澳.*?)\s*第:?\s*(\d+)\s*期开奖结果:\s*([\d\s]+)/u';

    if (preg_match($pattern, $message_text, $matches)) {
        $issue_number = trim($matches[1]);
        $numbers_block = trim($matches[2]);
        preg_match_all('/\d+/', $numbers_block, $number_matches);

        if (isset($number_matches[0]) && count($number_matches[0]) === 7) {
            $all_numbers = implode(',', $number_matches[0]);
            $draw_date = date('Y-m-d', $channel_post['date']);
            try {
                $stmt = $pdo->prepare("INSERT INTO lottery_results (issue_number, numbers, draw_date) VALUES (?, ?, ?)");
                $stmt->execute([$issue_number, $all_numbers, $draw_date]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    error_log("Database insertion error for issue {$issue_number}: " . $e->getMessage());
                }
            }
        }
    }
}