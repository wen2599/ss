<?php
// backend/telegram_webhook.php

// 强制错误报告，以便调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

@ini_set('max_execution_time', 120);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/telegram_bot_handler.php';
require_once __DIR__ . '/utils/lottery_rules.php';

error_log("--- Webhook Started ---");

// --- 安全性验证 ---
$secret_token_header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
$expected_token = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';

error_log("Received Secret Token: [" . $secret_token_header . "]");
error_log("Expected Secret Token: [" . $expected_token . "]");

if (empty($expected_token) || $secret_token_header !== $expected_token) {
    error_log("❌ Secret Token mismatch or not set. Aborting with 403.");
    http_response_code(403); 
    exit;
}
error_log("✅ Secret Token matched.");

$update_json = file_get_contents('php://input');
error_log("Received Request Body: " . $update_json);
$update = json_decode($update_json, true);

if (!$update) {
    error_log("❌ Failed to decode JSON from request body. Aborting.");
    http_response_code(400); // Bad Request
    exit;
}
error_log("✅ JSON body decoded successfully.");

// --- 路由更新 ---
try {
    $admin_id = $_ENV['TELEGRAM_ADMIN_ID'] ?? null;
    $chat_id = $update['message']['chat']['id'] ?? null;

    error_log("Configured ADMIN_ID: [" . $admin_id . "]");
    error_log("Incoming Chat ID: [" . $chat_id . "]");

    if (isset($update['message']['chat']['id']) && (string)$update['message']['chat']['id'] === (string)$admin_id) {
        error_log("Entering handleAdminCommand for chat ID: " . $chat_id);
        handleAdminCommand($update['message'], $pdo);
    } 
    elseif (isset($update['callback_query'])) {
        error_log("Entering handleCallbackQuery.");
        handleCallbackQuery($update['callback_query'], $pdo);
    }
    elseif (isset($update['channel_post']['text'])) {
        error_log("Entering processLotteryPost.");
        $new_issue_number = processLotteryPost($update['channel_post'], $pdo);
        if ($new_issue_number) {
            error_log("Triggering settlement for issue: " . $new_issue_number);
            triggerSettlement($new_issue_number, $pdo);
        }
    }
    else {
        error_log("No matching handler for the incoming update type or chat ID.");
        // You might want to send a default message or log the update for unhandled cases
    }
} catch (Exception $e) {
    error_log("❌ Webhook processing error: " . $e->getMessage());
    sendTelegramMessage("Webhook脚本出现严重错误: " . $e->getMessage());
}

http_response_code(200);
error_log("--- Webhook Finished ---");

// ===================================================================
// ==================== 业务逻辑函数 ====================
// ===================================================================

function handleAdminCommand($message, $pdo) {
    $text = $message['text'] ?? '';
    error_log("handleAdminCommand: Received text [" . $text . "]");
    
    $mainMenu = ['keyboard' => [['用户管理', '系统状态'], ['密钥管理']], 'resize_keyboard' => true, 'one_time_keyboard' => true];
    $backMenu = ['keyboard' => [['返回主菜单']], 'resize_keyboard' => true, 'one_time_keyboard' => true];

    switch ($text) {
        case '/start':
            error_log("handleAdminCommand: /start command recognized. Sending welcome message.");
            sendTelegramMessage("欢迎回来，管理员！", $mainMenu);
            break;

        case '用户管理':
            error_log("handleAdminCommand: 用户管理 command recognized.");
            sendTelegramMessage("请选择操作或输入用户邮箱进行查询：\n\n格式: `add 邮箱 密码` (添加用户)", $backMenu);
            break;

        case '系统状态':
            error_log("handleAdminCommand: 系统状态 command recognized.");
            try {
                $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $email_count = $pdo->query("SELECT COUNT(*) FROM user_emails")->fetchColumn();
                $batch_count = $pdo->query("SELECT COUNT(*) FROM email_batches WHERE status='new'")->fetchColumn();
                $lottery_latest = $pdo->query("SELECT issue_number FROM lottery_results ORDER BY issue_number DESC LIMIT 1")->fetchColumn();
                $status_message = "*系统状态报告*\n\n" . "👤 *注册用户:* `{$user_count}`\n" . "📧 *接收邮件:* `{$email_count}`\n" . "📋 *待处理批次:* `{$batch_count}`\n" . "🎲 *最新开奖:* `" . ($lottery_latest ?: 'N/A') . "`";
                sendTelegramMessage($status_message, $mainMenu);
                error_log("handleAdminCommand: System status sent.");
            } catch (PDOException $e) {
                error_log("handleAdminCommand: Query system status failed: " . $e->getMessage());
                sendTelegramMessage("查询状态失败: " . $e->getMessage()); 
            }
            break;
        
        case '密钥管理':
            error_log("handleAdminCommand: 密钥管理 command recognized.");
            sendTelegramMessage("请输入要设置的AI密钥：\n\n格式: `set_gemini_key 你的Gemini密钥`", $backMenu);
            break;

        case '返回主菜单':
            error_log("handleAdminCommand: 返回主菜单 command recognized.");
             sendTelegramMessage("已返回主菜单。", $mainMenu);
             break;

        default:
            error_log("handleAdminCommand: Default case entered for text [" . $text . "]");
            $parts = explode(' ', $text, 2); // Limit to 2 parts for key command
            $command = strtolower($parts[0]);

            if ($command === 'add' && count(explode(' ', $text, 4)) === 3) {
                error_log("handleAdminCommand: Add user command detected.");
                list(, $email, $password) = explode(' ', $text, 4);
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
                    $stmt->execute([$email, $password_hash]);
                    sendTelegramMessage("✅ 用户 `{$email}` 添加成功！");
                    error_log("handleAdminCommand: User added successfully.");
                } catch (PDOException $e) {
                    error_log("handleAdminCommand: Failed to add user: " . $e->getMessage());
                    sendTelegramMessage("❌ 添加用户失败: " . $e->getMessage()); 
                }
            } elseif ($command === 'set_gemini_key' && count($parts) === 2) {
                error_log("handleAdminCommand: Set Gemini key command detected.");
                $gemini_key = $parts[1];
                $env_file_path = __DIR__ . '/../.env';
                if (file_exists($env_file_path)) {
                    $env_content = file_get_contents($env_file_path);
                    $new_env_content = preg_replace(
                        '/^GEMINI_API_KEY=(.*)$/m',
                        "GEMINI_API_KEY={$gemini_key}",
                        $env_content, 1, $count
                    );

                    if ($count === 0) {
                        $new_env_content .= "\nGEMINI_API_KEY={$gemini_key}\n";
                    }
                    file_put_contents($env_file_path, $new_env_content);
                    sendTelegramMessage("✅ Gemini API 密钥已更新！");
                    error_log("handleAdminCommand: Gemini API key updated.");
                } else {
                    error_log("handleAdminCommand: .env file not found at [" . $env_file_path . "].");
                    sendTelegramMessage("❌ .env 文件不存在，无法更新密钥。");
                }
            } elseif (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                error_log("handleAdminCommand: Email query detected.");
                 try {
                    $stmt = $pdo->prepare("SELECT id, email, status, created_at FROM users WHERE email = ?");
                    $stmt->execute([$text]);
                    $user = $stmt->fetch();
                    if ($user) {
                        $user_message = "*找到用户:*\n\nID: `{$user['id']}`\n邮箱: `{$user['email']}`\n状态: `{$user['status']}`\n注册于: `{$user['created_at']}`";
                        $inline_keyboard = ['inline_keyboard' => [[['text' => '✅ 解封', 'callback_data' => "user_unban_{$user['id']}"], ['text' => '🚫 封禁', 'callback_data' => "user_ban_{$user['id']}"]], [['text' => '🗑️ 删除', 'callback_data' => "user_delete_{$user['id']}"]]]];
                        sendTelegramMessage($user_message, $inline_keyboard);
                        error_log("handleAdminCommand: User details sent.");
                    } else {
                        sendTelegramMessage("未找到邮箱为 `{$text}` 的用户。");
                        error_log("handleAdminCommand: User not found.");
                    }
                } catch (PDOException $e) {
                    error_log("handleAdminCommand: Query user failed: " . $e->getMessage());
                    sendTelegramMessage("查询用户失败：" . $e->getMessage()); 
                }
            } else {
                error_log("handleAdminCommand: Unrecognized command. Sending default message.");
                sendTelegramMessage("无法识别的指令。", $mainMenu);
            }
            break;
    }
}


function handleCallbackQuery($callback_query, $pdo) {
    error_log("handleCallbackQuery: Received callback data [" . $callback_query['data'] . "]");
    $callback_data = $callback_query['data'];
    $callback_query_id = $callback_query['id'];
    
    list($action, $target, $user_id, $confirm) = explode('_', $callback_data . '___');

    if ($action === 'delete' && $target === 'user' && $confirm !== 'confirm') {
        error_log("handleCallbackQuery: User delete confirmation requested for ID " . $user_id);
        answerCallbackQuery($callback_query_id);
        sendTelegramMessage("⚠️ *警告:*\n您确定要删除用户 ID: `{$user_id}` 吗？此操作会将用户状态设为 'deleted'。", [
            'inline_keyboard' => [[['text' => '✅ 是的，删除', 'callback_data' => "delete_user_{$user_id}_confirm"], ['text' => '❌ 取消', 'callback_data' => 'cancel_operation']]]
        ]);
        return;
    }
    
    if ($action === 'cancel') {
        error_log("handleCallbackQuery: Operation cancelled.");
        answerCallbackQuery($callback_query_id, "操作已取消");
        return;
    }

    $response_text = "未知操作";

    if ($target === 'user' && !empty($user_id)) {
        error_log("handleCallbackQuery: User action [" . $action . "] on user ID " . $user_id);
        try {
            $new_status = null;
            $operation_desc = '';
            if ($action === 'ban') { $new_status = 'suspended'; $operation_desc = '封禁'; }
            elseif ($action === 'unban') { $new_status = 'active'; $operation_desc = '解封'; }
            elseif ($action === 'delete' && $confirm === 'confirm') { $new_status = 'deleted'; $operation_desc = '删除'; }

            if ($new_status) {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                $response_text = "用户 ID:{$user_id} 已成功{$operation_desc}！";
                error_log("handleCallbackQuery: User ID " . $user_id . " status updated to " . $new_status);
            }
        } catch (PDOException $e) {
            error_log("handleCallbackQuery: User action failed: " . $e->getMessage());
            $response_text = "操作失败: " . $e->getMessage(); 
        }
    }
    
    answerCallbackQuery($callback_query_id, $response_text);
    error_log("handleCallbackQuery: Answered callback query.");
}


function processLotteryPost($channel_post, $pdo) {
    error_log("processLotteryPost: Received channel post.");
    $message_text = $channel_post['text'];
    $pattern = '/(?:新澳门六合彩|香港六合彩|老澳.*?)\s*第:?\s*(\d+)\s*期开奖结果:\s*([\d\s]+)/u';
    if (preg_match($pattern, $message_text, $matches)) {
        error_log("processLotteryPost: Lottery result pattern matched.");
        $issue_number = trim($matches[1]);
        $numbers_block = trim($matches[2]);
        preg_match_all('/\d+/', $numbers_block, $number_matches);

        if (isset($number_matches[0]) && count($number_matches[0]) === 7) {
            $all_numbers = implode(',', $number_matches[0]);
            $draw_date = date('Y-m-d', $channel_post['date']);
            try {
                $stmt = $pdo->prepare("INSERT INTO lottery_results (issue_number, numbers, draw_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numbers=VALUES(numbers)");
                $stmt->execute([$issue_number, $all_numbers, $draw_date]);
                error_log("processLotteryPost: Lottery result for issue " . $issue_number . " saved.");
                return $issue_number;
            } catch (PDOException $e) {
                error_log("processLotteryPost: DB insert error for issue {$issue_number}: " . $e->getMessage());
            }
        }
    }
    error_log("processLotteryPost: No lottery result processed.");
    return false;
}


function triggerSettlement($issue_number, $pdo) {
    error_log("triggerSettlement: Starting settlement for issue " . $issue_number);
    try {
        $stmt = $pdo->prepare("SELECT numbers FROM lottery_results WHERE issue_number = ?");
        $stmt->execute([$issue_number]);
        $lottery_numbers_str = $stmt->fetchColumn();
        if (!$lottery_numbers_str) { 
            sendTelegramMessage("❌ 结算失败！期号 `{$issue_number}` 数据库中找不到开奖结果。");
            error_log("❌ triggerSettlement: DB not found result for issue {$issue_number}"); 
            throw new Exception("DB not found result for issue {$issue_number}"); 
        }
        
        $lottery_numbers = explode(',', $lottery_numbers_str);
        $special_number = (int)end($lottery_numbers);

        $batches_to_settle = $pdo->prepare(
            "SELECT eb.id, eb.user_id, eb.parsed_data, u.odds_settings 
             FROM email_batches eb 
             JOIN users u ON eb.user_id = u.id
             WHERE (eb.issue_number = ? OR eb.issue_number IS NULL) 
               AND (eb.status = 'parsed' OR eb.status = 'manual_override')
             AND u.odds_settings IS NOT NULL AND u.odds_settings != ''"
        );
        $batches_to_settle->execute([$issue_number]);
        
        $settled_count = 0; 
        $total_payout = 0;
        $skipped_batches = []; 
        error_log("triggerSettlement: Found " . $batches_to_settle->rowCount() . " batches to potentially settle.");

        while ($batch = $batches_to_settle->fetch(PDO::FETCH_ASSOC)) {
            $user_id = $batch['user_id'];
            $user_odds_settings = json_decode($batch['odds_settings'], true); 

            if (empty($user_odds_settings)) {
                $skipped_batches[] = $batch['id'];
                error_log("triggerSettlement: Skipping batch {$batch['id']} for user {$user_id}: no odds_settings found.");
                continue; 
            }

            $parsed_data = json_decode($batch['parsed_data'], true);
            $bets = $parsed_data['bets'] ?? [];
            $batch_payout = 0;
            $winning_bets = [];

            foreach ($bets as $bet) {
                $payout = 0;
                $selection = $bet['selection'] ?? '';
                $amount = (float)($bet['amount'] ?? 0);
                
                $bet_type = $bet['type'] ?? '';

                switch ($bet_type) {
                    case '特码':
                        if ($selection == $special_number) {
                            $payout = $amount * ($user_odds_settings['特码']['特码'] ?? 0);
                        }
                        break;
                    case '平特肖':
                        $special_zodiac = LotteryHelper::getZodiac($special_number);
                        if ($selection == $special_zodiac) {
                            $payout = $amount * ($user_odds_settings['平特肖'][$selection] ?? 0);
                        }
                        break;
                }
                
                if ($payout > 0) {
                    $batch_payout += $payout;
                    $winning_bets[] = ['bet' => $bet, 'payout' => $payout];
                }
            }
            
            $settlement_result_data = ['is_win' => count($winning_bets) > 0, 'payout' => $batch_payout, 'details' => $winning_bets, 'settled_at' => date('Y-m-d H:i:s')];
            $updateStmt = $pdo->prepare("UPDATE email_batches SET settlement_result = ?, status = 'settled', issue_number = ? WHERE id = ?");
            $updateStmt->execute([json_encode($settlement_result_data), $issue_number, $batch['id']]);
            
            $settled_count++; $total_payout += $batch_payout;
            error_log("triggerSettlement: Batch {$batch['id']} settled. Payout: " . $batch_payout);
        }

        if ($settled_count > 0) {
            $message = "✅ *结算完成!*\n\n期号: `{$issue_number}`\n处理投注数: `{$settled_count}`\n总派奖: `{$total_payout}`";
            sendTelegramMessage($message);
            error_log("triggerSettlement: Settlement success message sent.");
        } else {
            $message = "ℹ️ 期号 `{$issue_number}` 已开奖，无待结算投注单。";
            if (!empty($skipped_batches)) {
                $message .= "\n(其中 `" . count($skipped_batches) . "` 条投注因用户未设置赔率而被跳过。)";
            }
            sendTelegramMessage($message);
            error_log("triggerSettlement: No batches settled message sent.");
        }
    } catch (Exception $e) {
        error_log("❌ triggerSettlement: Settlement error for issue {$issue_number}: " . $e->getMessage());
        sendTelegramMessage("❌ *结算失败!*\n\n期号 `{$issue_number}` 发生错误: \n`" . $e->getMessage() . "`");
    }
}