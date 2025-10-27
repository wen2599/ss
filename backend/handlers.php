<?php

declare(strict_types=1);

// backend/handlers.php

// [MODIFIED] 引入新的结算规则文件
require_once __DIR__ . '/settlement_rules.php';


/**
 * Handles the /help and /start command.
 */
function handle_help_command($chat_id): void
{
    $reply_text = "您好, 管理员！请使用下面的菜单进行操作:\n\n" .
                  "<b>--- 核心功能 ---</b>\n" .
                  "/settle [期号] - 对指定期号进行结算\n" .
                  "/report [期号] - 查看指定期号的结算报告\n" .
                  "/latest - 查询最新一条开奖记录\n" .
                  "/add [类型] [期号] [号码] - 添加开奖记录\n\n" .
                  "<b>--- 管理 ---</b>\n" .
                  "/stats - 查看系统统计数据\n" .
                  "/finduser [关键词] - 查找用户信息\n\n" .
                  "<b>--- AI 功能 ---</b>\n" .
                  "/setgeminikey [API密钥] - 设置Gemini API Key\n" .
                  "/cfchat [问题] - 与Cloudflare AI对话\n";

    $keyboard = [
        'keyboard' => [
            [['text' => '结算'], ['text' => '结算报告']],
            [['text' => '最新开奖'], ['text' => '系统统计']],
            [['text' => 'CF AI 对话'], ['text' => '更换Gemini Key']],
            [['text' => '帮助说明']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
        'selective' => true
    ];

    $reply_markup = json_encode($keyboard);
    send_telegram_message($chat_id, $reply_text, $reply_markup, "HTML");
}

// ... (Existing functions like handle_stats_command, handle_latest_command, etc. remain here) ...


/**
 * [MODIFIED] Handles the /settle command to process settlements for a specific draw period.
 */
function handle_settle_command($chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误。用法: /settle [期号]");
        return;
    }
    $draw_period = $command_parts[1];

    send_telegram_message($chat_id, "收到请求！正在开始为期号 {$draw_period} 进行结算...");

    $result = process_settlements_for_draw($draw_period);

    if ($result === null) {
        send_telegram_message($chat_id, "❌ 结算失败: 未能找到期号为 {$draw_period} 的开奖记录。请先添加该期的开奖号码。");
        return;
    }

    if ($result['settled_count'] === 0) {
        send_telegram_message($chat_id, "ℹ️ 期号 {$draw_period} 没有找到待结算的单据。");
        return;
    }

    $net_profit = $result['total_bets'] - $result['total_winnings'];
    $profit_emoji = $net_profit >= 0 ? '🟢' : '🔴';

    $reply_text = "✅ <b>期号 {$draw_period} 结算完成！</b>\n\n" .
                  "- 结算单据数: {$result['settled_count']} 张\n" .
                  "- 总投注额: " . number_format($result['total_bets'], 2) . "\n" .
                  "- 总派奖额: " . number_format($result['total_winnings'], 2) . "\n" .
                  "- {$profit_emoji} 本期利润: " . number_format($net_profit, 2);

    send_telegram_message($chat_id, $reply_text, null, 'HTML');
}

/**
 * [MODIFIED] Handles the /report command to show a summary for a settled draw period.
 */
function handle_report_command($chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误。用法: /report [期号]");
        return;
    }
    $draw_period = $command_parts[1];

    $report = generate_settlement_report($draw_period);

    if ($report === null) {
        send_telegram_message($chat_id, "❌ 未能生成报告: 未找到任何与期号 {$draw_period} 相关的已结算单据。");
        return;
    }

    $net_profit = $report['total_bets'] - $report['total_winnings'];
    $profit_emoji = $net_profit >= 0 ? '🟢' : '🔴';

    $reply_text = "📊 <b>期号 {$draw_period} 结算报告</b>\n\n" .
                  "- 已结算单据: {$report['settled_count']} 张\n" .
                  "- 总投注额: " . number_format($report['total_bets'], 2) . "\n" .
                  "- 总派奖额: " . number_format($report['total_winnings'], 2) . "\n" .
                  "- {$profit_emoji} 本期利润: " . number_format($net_profit, 2);
    
    send_telegram_message($chat_id, $reply_text, null, 'HTML');
}

// --- Helper functions for settlement --- 

/**
 * [MODIFIED] Core settlement processing logic for a given draw period.
 */
function process_settlements_for_draw(string $draw_period): ?array
{
    global $db_connection;
    
    // 1. Get lottery winning numbers
    $stmt_draw = $db_connection->prepare("SELECT numbers FROM lottery_draws WHERE draw_period = ?");
    $stmt_draw->bind_param("s", $draw_period);
    $stmt_draw->execute();
    $result_draw = $stmt_draw->get_result();
    if (!($draw = $result_draw->fetch_assoc())) {
        return null; // Draw not found
    }
    $winning_numbers = array_map('intval', explode(',', $draw['numbers']));
    $stmt_draw->close();

    // 2. Get all pending settlements for this draw
    $stmt_settlements = $db_connection->prepare("SELECT id, settlement_data FROM settlements WHERE draw_period = ? AND status = 'pending_settlement'");
    $stmt_settlements->bind_param("s", $draw_period);
    $stmt_settlements->execute();
    $pending_settlements = $stmt_settlements->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_settlements->close();

    if (empty($pending_settlements)) {
        return ['settled_count' => 0, 'total_bets' => 0, 'total_winnings' => 0];
    }

    $total_winnings_all = 0;
    $total_bets_all = 0;
    
    $db_connection->begin_transaction();
    try {
        foreach ($pending_settlements as $settlement) {
            $settlement_id = $settlement['id'];
            $bets = json_decode($settlement['settlement_data'], true);
            $total_winnings_single = 0;

            foreach ($bets as &$bet) { // Pass by reference to update
                $winnings = calculate_winnings($bet, $winning_numbers);
                $bet['winnings'] = $winnings;
                $bet['status'] = $winnings > 0 ? '中奖' : '未中奖'; // 更新状态
                $total_winnings_single += $winnings;
                $total_bets_all += floatval($bet['amount']);
            }
            unset($bet); // Unset reference

            $total_winnings_all += $total_winnings_single;

            // Update settlement in DB
            $stmt_update = $db_connection->prepare("UPDATE settlements SET total_winnings = ?, settlement_data = ?, status = 'settled' WHERE id = ?");
            $updated_data_json = json_encode($bets);
            $stmt_update->bind_param("dsi", $total_winnings_single, $updated_data_json, $settlement_id);
            $stmt_update->execute();
            $stmt_update->close();
        }
        $db_connection->commit();
    } catch (Exception $e) {
        $db_connection->rollback();
        error_log("Settlement failed: " . $e->getMessage());
        return null;
    }

    return [
        'settled_count' => count($pending_settlements),
        'total_bets' => $total_bets_all,
        'total_winnings' => $total_winnings_all
    ];
}

/**
 * [MODIFIED] Generates a report for an already settled draw period.
 */
function generate_settlement_report(string $draw_period): ?array
{
    global $db_connection;
    $stmt = $db_connection->prepare("SELECT COUNT(id) as settled_count, SUM(total_amount) as total_bets, SUM(total_winnings) as total_winnings FROM settlements WHERE draw_period = ? AND status = 'settled'");
    $stmt->bind_param("s", $draw_period);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (empty($result) || $result['settled_count'] == 0) {
        return null;
    }

    return [
        'settled_count' => (int)$result['settled_count'],
        'total_bets' => (float)$result['total_bets'],
        'total_winnings' => (float)$result['total_winnings']
    ];
}


// [REMOVED] The old placeholder for calculate_winnings is now removed.

// ... (rest of the existing functions: handle_stats_command, handle_add_command, etc.)

