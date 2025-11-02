<?php
// backend/telegram_webhook.php

@ini_set('max_execution_time', 120);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/telegram_bot_handler.php';

// --- DEBUGGING: Log incoming request details ---
$secret_token_header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
$expected_token = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
$update_json = file_get_contents('php://input');

error_log("--- Incoming Webhook Request ---");
error_log("Received Secret Token: " . $secret_token_header);
error_log("Expected Secret Token: " . $expected_token);
error_log("Request Body: " . $update_json);
// --- END DEBUGGING ---

// --- 安全性：验证来自Telegram的秘密Token ---
if (empty($expected_token) || $secret_token_header !== $expected_token) {
    error_log("Secret Token mismatch. Aborting.");
    http_response_code(403);
    exit;
}

// --- 获取并处理更新 ---
$update = json_decode($update_json, true);

if (!$update) {
    error_log("Failed to decode JSON from request body. Aborting.");
    exit;
}

// --- 路由更新到相应的处理器 ---
try {
    if (isset($update['message']['chat']['id']) && $update['message']['chat']['id'] == $_ENV['TELEGRAM_ADMIN_ID']) {
        $text = $update['message']['text'];
        handleAdminCommand($text, $pdo);
    } 
    elseif (isset($update['callback_query'])) {
        $callback_data = $update['callback_query']['data'];
        $callback_query_id = $update['callback_query']['id'];
        handleCallbackQuery($callback_data, $callback_query_id, $pdo);
    }
    elseif (isset($update['channel_post']['text'])) {
        $new_issue_number = processLotteryPost($update['channel_post'], $pdo);
        if ($new_issue_number) {
            triggerSettlement($new_issue_number, $pdo);
        }
    }
} catch (Exception $e) {
    error_log("Webhook processing error: " . $e->getMessage());
    sendTelegramMessage("Webhook脚本出现严重错误: " . $e->getMessage());
}

http_response_code(200);


// ===================================================================
// ==================== 业务逻辑函数 ====================
// ===================================================================

/**
 * 处理管理员的文本命令/菜单点击
 */
function handleAdminCommand($text, $pdo) {
    $mainMenu = [
        'keyboard' => [['刷新开奖', '用户管理'], ['系统状态']],
        'resize_keyboard' => true
    ];

    switch ($text) {
        case '/start':
            sendTelegramMessage("欢迎回来，管理员！请选择操作：", $mainMenu);
            break;

        case '刷新开奖':
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
                                  "📧 *接收邮件总数:* `{$email_count}`\n" .
                                  "📋 *待处理批次:* `{$batch_count}`\n" .
                                  "🎲 *最新开奖期号:* `{$lottery_count}`";
                sendTelegramMessage($status_message);
            } catch (PDOException $e) {
                sendTelegramMessage("查询系统状态失败：" . $e->getMessage());
            }
            break;

        default:
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
    list($action, $target, $user_id) = explode('_', $callback_data . '_'); // Added padding to avoid undefined offset
    
    $response_text = "未知操作";

    if ($target === 'user' && !empty($user_id)) {
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
    
    answerCallbackQuery($callback_query_id, $response_text);
}

/**
 * 处理频道开奖消息
 */
function processLotteryPost($channel_post, $pdo) {
    $message_text = $channel_post['text'];
    $pattern = '/(?:新澳门六合彩|香港六合彩|老澳.*?)\s*第:?\s*(\d+)\s*期开奖结果:\s*([\d\s]+)/u';
    $issue_number = null;

    if (preg_match($pattern, $message_text, $matches)) {
        $issue_number = trim($matches[1]);
        $numbers_block = trim($matches[2]);
        preg_match_all('/\d+/', $numbers_block, $number_matches);

        if (isset($number_matches[0]) && count($number_matches[0]) === 7) {
            $all_numbers = implode(',', $number_matches[0]);
            $draw_date = date('Y-m-d', $channel_post['date']);
            try {
                $stmt = $pdo->prepare("INSERT INTO lottery_results (issue_number, numbers, draw_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numbers=numbers");
                $stmt->execute([$issue_number, $all_numbers, $draw_date]);
            } catch (PDOException $e) {
                error_log("Database insertion error for issue {$issue_number}: " . $e->getMessage());
                return null; // Don't proceed on DB error
            }
        } else {
            return null; // Numbers parsing failed
        }
    }
    return $issue_number;
}

/**
 * 触发并执行结算流程
 */
function triggerSettlement($issue_number, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT numbers FROM lottery_results WHERE issue_number = ?");
        $stmt->execute([$issue_number]);
        $lottery_numbers_str = $stmt->fetchColumn();
        if (!$lottery_numbers_str) {
            throw new Exception("在数据库中找不到期号 {$issue_number} 的开奖结果。");
        }
        $lottery_numbers = explode(',', $lottery_numbers_str);
        
        $batches_to_settle = $pdo->prepare(
            "SELECT id, parsed_data FROM email_batches WHERE (issue_number = ? OR issue_number IS NULL) AND (status = 'parsed' OR status = 'manual_override')"
        );
        $batches_to_settle->execute([$issue_number]);
        
        $settled_count = 0;
        $total_payout = 0;

        while ($batch = $batches_to_settle->fetch(PDO::FETCH_ASSOC)) {
            $parsed_data = json_decode($batch['parsed_data'], true);
            $bets = $parsed_data['bets'] ?? [];
            
            $batch_win = false;
            $batch_payout = 0;
            
            foreach ($bets as $bet) {
                if (isset($bet['type']) && $bet['type'] === '特码' && in_array($bet['selection'], $lottery_numbers)) {
                    $payout = (float)($bet['amount'] ?? 0) * 40;
                    $batch_payout += $payout;
                    $batch_win = true;
                }
            }
            
            $settlement_result_data = [
                'is_win' => $batch_win,
                'payout' => $batch_payout,
                'settled_at' => date('Y-m-d H:i:s')
            ];
            $settlement_result_json = json_encode($settlement_result_data);
            
            $updateStmt = $pdo->prepare("UPDATE email_batches SET settlement_result = ?, status = 'settled', issue_number = ? WHERE id = ?");
            $updateStmt->execute([$settlement_result_json, $issue_number, $batch['id']]);
            
            $settled_count++;
            $total_payout += $batch_payout;
        }

        if ($settled_count > 0) {
            $message = "✅ *结算完成!*\n\n" .
                       "期号: `{$issue_number}`\n" .
                       "处理投注数: `{$settled_count}`\n" .
                       "总派奖: `{$total_payout}`";
            sendTelegramMessage($message);
        } else {
            sendTelegramMessage("ℹ️ 期号 `{$issue_number}` 已开奖，但没有找到需要结算的投注单。");
        }

    } catch (Exception $e) {
        error_log("Settlement error for issue {$issue_number}: " . $e->getMessage());
        sendTelegramMessage("❌ *结算失败!*\n\n期号 `{$issue_number}` 在结算过程中发生错误: \n`" . $e->getMessage() . "`");
    }
}