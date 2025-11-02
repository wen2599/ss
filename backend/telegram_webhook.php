<?php
// backend/telegram_webhook.php

// 增加执行时间，以防结算任务超时 (根据服务器配置，不一定有效)
@ini_set('max_execution_time', 120); // 尝试设置为120秒

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/telegram_bot_handler.php';

// --- 安全性：验证来自Telegram的秘密Token ---
$secret_token_header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
$expected_token = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';

if (empty($expected_token) || $secret_token_header !== $expected_token) {
    http_response_code(403);
    exit;
}

// --- 获取并处理更新 ---
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

if (!$update) {
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
            // 收到新的开奖结果，立即触发结算
            triggerSettlement($new_issue_number, $pdo);
        }
    }
} catch (Exception $e) {
    error_log("Webhook processing error: " . $e->getMessage());
    // 向管理员报告错误
    sendTelegramMessage("Webhook脚本出现严重错误: " . $e->getMessage());
}

// 必须给Telegram一个200 OK响应
http_response_code(200);


// ===================================================================
// ==================== 业务逻辑函数 ====================
// ===================================================================

// ** handleAdminCommand(...) 函数 **
// (从之前的版本完整复制过来)
function handleAdminCommand($text, $pdo) { /* ... (完整代码) ... */ }

// ** handleCallbackQuery(...) 函数 **
// (从之前的版本完整复制过来)
function handleCallbackQuery($callback_data, $callback_query_id, $pdo) { /* ... (完整代码) ... */ }

// ** processLotteryPost(...) 函数 **
// (从之前的版本完整复制过来)
function processLotteryPost($channel_post, $pdo) { /* ... (完整代码) ... */ }


/**
 * 触发并执行结算流程
 * @param string $issue_number 要结算的期号
 * @param PDO $pdo 数据库连接
 */
function triggerSettlement($issue_number, $pdo) {
    try {
        // 1. 获取该期的开奖号码
        $stmt = $pdo->prepare("SELECT numbers FROM lottery_results WHERE issue_number = ?");
        $stmt->execute([$issue_number]);
        $lottery_numbers_str = $stmt->fetchColumn();
        if (!$lottery_numbers_str) {
            throw new Exception("在数据库中找不到期号 {$issue_number} 的开奖结果。");
        }
        $lottery_numbers = explode(',', $lottery_numbers_str);
        
        // 2. 查找所有与该期数相关的、待结算的批次
        $batches_to_settle = $pdo->prepare(
            "SELECT id, parsed_data FROM email_batches WHERE (issue_number = ? OR issue_number IS NULL) AND (status = 'parsed' OR status = 'manual_override')"
        );
        // **重要**：我们结算所有`issue_number`匹配或为`NULL`的批次
        // 这符合我们之前“永远对最新一期结算”的策略
        $batches_to_settle->execute([$issue_number]);
        
        $settled_count = 0;
        $total_payout = 0;

        while ($batch = $batches_to_settle->fetch(PDO::FETCH_ASSOC)) {
            $parsed_data = json_decode($batch['parsed_data'], true);
            $bets = $parsed_data['bets'] ?? [];
            
            $batch_win = false;
            $batch_payout = 0;
            
            // 3. 在这里执行您的结算核心逻辑
            // 遍历 $bets 数组中的每一条投注
            foreach ($bets as $bet) {
                // TODO: 根据 $bet['type'], $bet['selection'] 和 $lottery_numbers 判断是否中奖
                // 这是一个需要根据您的具体彩票规则来实现的复杂逻辑
                // 下面是一个极简的示例：
                if (isset($bet['type']) && $bet['type'] === '特码' && in_array($bet['selection'], $lottery_numbers)) {
                    $payout = (float)($bet['amount'] ?? 0) * 40; // 假设赔率是40
                    $batch_payout += $payout;
                    $batch_win = true;
                }
                // ... 在这里添加更多其他玩法的判断逻辑 ...
            }
            
            // 4. 构建结算结果JSON
            $settlement_result_data = [
                'is_win' => $batch_win,
                'payout' => $batch_payout,
                'settled_at' => date('Y-m-d H:i:s')
            ];
            $settlement_result_json = json_encode($settlement_result_data);
            
            // 5. 更新批次状态和结果
            $updateStmt = $pdo->prepare("UPDATE email_batches SET settlement_result = ?, status = 'settled' WHERE id = ?");
            $updateStmt->execute([$settlement_result_json, $batch['id']]);
            
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
            // 即使没有需要结算的单，也通知一下
            sendTelegramMessage("ℹ️ 期号 `{$issue_number}` 已开奖，但没有找到需要结算的投注单。");
        }

    } catch (Exception $e) {
        error_log("Settlement error for issue {$issue_number}: " . $e->getMessage());
        sendTelegramMessage("❌ *结算失败!*\n\n期号 `{$issue_number}` 在结算过程中发生错误: \n`" . $e->getMessage() . "`");
    }
}