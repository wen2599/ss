<?php
// backend/telegram_webhook.php

// 强制错误报告，以便调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

@ini_set('max_execution_time', 120);

// 确保所有依赖文件都存在
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/telegram_bot_handler.php';
require_once __DIR__ . '/utils/lottery_rules.php'; // 引入新的规则引擎

// --- 安全性验证 ---
$secret_token_header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
$expected_token = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
if (empty($expected_token) || $secret_token_header !== $expected_token) {
    http_response_code(403); exit;
}

$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);
if (!$update) { exit; }

// --- 路由更新 ---
try {
    if (isset($update['message']['chat']['id']) && $update['message']['chat']['id'] == $_ENV['TELEGRAM_ADMIN_ID']) {
        handleAdminCommand($update['message'], $pdo);
    } 
    elseif (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query'], $pdo);
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

function handleAdminCommand($message, $pdo) {
    $text = $message['text'] ?? '';
    
    // 移除赔率管理，增加密钥管理
    $mainMenu = ['keyboard' => [['用户管理', '系统状态'], ['密钥管理']], 'resize_keyboard' => true, 'one_time_keyboard' => true];
    $backMenu = ['keyboard' => [['返回主菜单']], 'resize_keyboard' => true, 'one_time_keyboard' => true];

    switch ($text) {
        case '/start':
            sendTelegramMessage("欢迎回来，管理员！", $mainMenu);
            break;

        case '用户管理':
            sendTelegramMessage("请选择操作或输入用户邮箱进行查询：\n\n格式: `add 邮箱 密码` (添加用户)", $backMenu);
            break;

        case '系统状态':
            try {
                $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $email_count = $pdo->query("SELECT COUNT(*) FROM user_emails")->fetchColumn();
                $batch_count = $pdo->query("SELECT COUNT(*) FROM email_batches WHERE status='new'")->fetchColumn();
                $lottery_latest = $pdo->query("SELECT issue_number FROM lottery_results ORDER BY issue_number DESC LIMIT 1")->fetchColumn();
                $status_message = "*系统状态报告*\n\n" . "👤 *注册用户:* `{$user_count}`\n" . "📧 *接收邮件:* `{$email_count}`\n" . "📋 *待处理批次:* `{$batch_count}`\n" . "🎲 *最新开奖:* `" . ($lottery_latest ?: 'N/A') . "`";
                sendTelegramMessage($status_message, $mainMenu);
            } catch (PDOException $e) { sendTelegramMessage("查询状态失败: " . $e->getMessage()); }
            break;
        
        case '密钥管理':
            sendTelegramMessage("请输入要设置的AI密钥：\n\n格式: `set_gemini_key 你的Gemini密钥`", $backMenu);
            break;

        case '返回主菜单':
             sendTelegramMessage("已返回主菜单。", $mainMenu);
             break;

        default:
            $parts = explode(' ', $text, 2); // Limit to 2 parts for key command
            $command = strtolower($parts[0]);

            if ($command === 'add' && count(explode(' ', $text, 4)) === 3) {
                list(, $email, $password) = explode(' ', $text, 4);
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
                    $stmt->execute([$email, $password_hash]);
                    sendTelegramMessage("✅ 用户 `{$email}` 添加成功！");
                } catch (PDOException $e) { sendTelegramMessage("❌ 添加用户失败: " . $e->getMessage()); }
            } elseif ($command === 'set_gemini_key' && count($parts) === 2) {
                $gemini_key = $parts[1];
                // 假设 .env 文件在项目根目录
                $env_file_path = __DIR__ . '/../.env';
                if (file_exists($env_file_path)) {
                    $env_content = file_get_contents($env_file_path);
                    // 使用正则表达式替换 GEMINI_API_KEY
                    $new_env_content = preg_replace(
                        '/^GEMINI_API_KEY=(.*)$/m',
                        "GEMINI_API_KEY={$gemini_key}",
                        $env_content, 1, $count
                    );

                    if ($count === 0) {
                        // 如果不存在，则添加到文件末尾
                        $new_env_content .= "\nGEMINI_API_KEY={$gemini_key}\n";
                    }
                    file_put_contents($env_file_path, $new_env_content);
                    sendTelegramMessage("✅ Gemini API 密钥已更新！");
                } else {
                    sendTelegramMessage("❌ .env 文件不存在，无法更新密钥。");
                }
            } elseif (filter_var($text, FILTER_VALIDATE_EMAIL)) {
                 try {
                    $stmt = $pdo->prepare("SELECT id, email, status, created_at FROM users WHERE email = ?");
                    $stmt->execute([$text]);
                    $user = $stmt->fetch();
                    if ($user) {
                        $user_message = "*找到用户:*\n\nID: `{$user['id']}`\n邮箱: `{$user['email']}`\n状态: `{$user['status']}`\n注册于: `{$user['created_at']}`";
                        $inline_keyboard = ['inline_keyboard' => [[['text' => '✅ 解封', 'callback_data' => "user_unban_{$user['id']}"], ['text' => '🚫 封禁', 'callback_data' => "user_ban_{$user['id']}"]], [['text' => '🗑️ 删除', 'callback_data' => "user_delete_{$user['id']}"]]]];
                        sendTelegramMessage($user_message, $inline_keyboard);
                    } else { sendTelegramMessage("未找到邮箱为 `{$text}` 的用户。"); }
                } catch (PDOException $e) { sendTelegramMessage("查询用户失败：" . $e->getMessage()); }
            } else {
                sendTelegramMessage("无法识别的指令。", $mainMenu);
            }
            break;
    }
}


function handleCallbackQuery($callback_query, $pdo) {
    $callback_data = $callback_query['data'];
    $callback_query_id = $callback_query['id'];
    
    list($action, $target, $user_id, $confirm) = explode('_', $callback_data . '___');

    if ($action === 'delete' && $target === 'user' && $confirm !== 'confirm') {
        answerCallbackQuery($callback_query_id);
        sendTelegramMessage("⚠️ *警告:*\n您确定要删除用户 ID: `{$user_id}` 吗？此操作会将用户状态设为 'deleted'。", [
            'inline_keyboard' => [[['text' => '✅ 是的，删除', 'callback_data' => "delete_user_{$user_id}_confirm"], ['text' => '❌ 取消', 'callback_data' => 'cancel_operation']]]
        ]);
        return;
    }
    
    if ($action === 'cancel') {
        answerCallbackQuery($callback_query_id, "操作已取消");
        return;
    }

    $response_text = "未知操作";

    if ($target === 'user' && !empty($user_id)) {
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
            }
        } catch (PDOException $e) { $response_text = "操作失败: " . $e->getMessage(); }
    }
    
    answerCallbackQuery($callback_query_id, $response_text);
}


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
                $stmt = $pdo->prepare("INSERT INTO lottery_results (issue_number, numbers, draw_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numbers=VALUES(numbers)");
                $stmt->execute([$issue_number, $all_numbers, $draw_date]);
                return $issue_number;
            } catch (PDOException $e) {
                error_log("DB insert error for issue {$issue_number}: " . $e->getMessage());
            }
        }
    }
    return false;
}


function triggerSettlement($issue_number, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT numbers FROM lottery_results WHERE issue_number = ?");
        $stmt->execute([$issue_number]);
        $lottery_numbers_str = $stmt->fetchColumn();
        if (!$lottery_numbers_str) { 
            sendTelegramMessage("❌ 结算失败！期号 `{$issue_number}` 数据库中找不到开奖结果。");
            throw new Exception("DB not found result for issue {$issue_number}"); 
        }
        
        $lottery_numbers = explode(',', $lottery_numbers_str);
        $special_number = (int)end($lottery_numbers);

        // 不再从全局odds表获取赔率
        // $odds_map = [];
        // foreach ($pdo->query("SELECT play_type, name, odds_value FROM odds WHERE is_enabled=1") as $row) {
        //     $odds_map[$row['play_type']][$row['name']] = (float)$row['odds_value'];
        // }

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
        $skipped_batches = []; // 用于记录因无赔率而被跳过的批次

        while ($batch = $batches_to_settle->fetch(PDO::FETCH_ASSOC)) {
            $user_id = $batch['user_id'];
            $user_odds_settings = json_decode($batch['odds_settings'], true); // 获取用户专属赔率

            if (empty($user_odds_settings)) {
                $skipped_batches[] = $batch['id'];
                error_log("Skipping batch {$batch['id']} for user {$user_id}: no odds_settings found.");
                continue; // 跳过没有赔率设置的用户
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
                            // 使用用户专属赔率
                            $payout = $amount * ($user_odds_settings['特码']['特码'] ?? 0);
                        }
                        break;
                    case '平特肖':
                        $special_zodiac = LotteryHelper::getZodiac($special_number);
                        if ($selection == $special_zodiac) {
                            // 使用用户专属赔率
                            $payout = $amount * ($user_odds_settings['平特肖'][$selection] ?? 0);
                        }
                        break;
                    // TODO: 根据需要添加更多玩法和对应的赔率获取逻辑
                    // 例如：case '红波': if (LotteryHelper::getColor($special_number) === 'red') { $payout = $amount * ($user_odds_settings['波色']['红波'] ?? 0); } break;
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
        }

        if ($settled_count > 0) {
            $message = "✅ *结算完成!*\n\n期号: `{$issue_number}`\n处理投注数: `{$settled_count}`\n总派奖: `{$total_payout}`";
            sendTelegramMessage($message);
        } else {
            $message = "ℹ️ 期号 `{$issue_number}` 已开奖，无待结算投注单。";
            if (!empty($skipped_batches)) {
                $message .= "\n(其中 `" . count($skipped_batches) . "` 条投注因用户未设置赔率而被跳过。)";
            }
            sendTelegramMessage($message);
        }
    } catch (Exception $e) {
        error_log("Settlement error for issue {$issue_number}: " . $e->getMessage());
        sendTelegramMessage("❌ *结算失败!*\n\n期号 `{$issue_number}` 发生错误: \n`" . $e->getMessage() . "`");
    }
}