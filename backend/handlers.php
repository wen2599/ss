<?php

declare(strict_types=1);

// backend/handlers.php

require_once __DIR__ . '/settlement_rules.php';

/**
 * Handles the /help and /start command.
 */
function handle_help_command($chat_id): void
{
    $reply_text = "您好, 管理员！请使用下面的菜单进行操作或直接输入命令：\\n\\n" .
                  "<b>--- 核心业务 ---</b>\\n" .
                  "/settle [期号] - 执行指定期号的结算\\n" .
                  "/report [期号] - 获取指定期号的结算报告\\n" .
                  "/latest - 查询最新开奖记录\\n" .
                  "/add [类型] [期号] [号码] - 手动添加开奖记录\\n" .
                  "/delete [类型] [期号] - 删除开奖记录\\n\\n" .
                  "<b>--- 用户管理 ---</b>\\n" .
                  "/stats - 查看系统概况\\n" .
                  "/finduser [关键词] - 查找用户 (用户名/邮箱)\\n" .
                  "/deleteuser [关键词] - 删除用户及所有数据\\n\\n" .
                  "<b>--- AI 助手 ---</b>\\n" .
                  "/setgeminikey [密钥] - 配置Gemini API Key\\n" .
                  "/cfchat [问题] - 与Cloudflare AI对话\\n" .
                  "/geminichat [问题] - 与Gemini AI对话\\n" .
                  "/help - 显示此帮助信息";

    $keyboard = [
        'keyboard' => [
            [['text' => '结算'], ['text' => '结算报告']],
            [['text' => '最新开奖'], ['text' => '系统统计']],
            [['text' => '查找用户'], ['text' => '删除用户']],
            [['text' => 'CF AI 对话'], ['text' => 'Gemini AI 对话']],
            [['text' => '更换Gemini Key'], ['text' => '帮助说明']],
            [['text' => '退出会话']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
        'selective' => true
    ];

    $reply_markup = json_encode($keyboard);
    send_telegram_message($chat_id, $reply_text, $reply_markup, "HTML");
}

/**
 * Handles the /stats command.
 */
function handle_stats_command($chat_id): void
{
    global $db_connection; // 确保 db_connection 可用
    $stats = get_system_stats();
    $reply_text = "📊 系统统计数据:\\n" .
                  "  - 注册用户数: {$stats['users']}\\n" .
                  "  - 已保存邮件数: {$stats['emails']}\\n" .
                  "  - 开奖记录数: {$stats['lottery_draws']}";
    send_telegram_message($chat_id, $reply_text);
}

/**
 * Handles the /latest command.
 */
function handle_latest_command($chat_id): void
{
    global $db_connection;
    $query = "SELECT draw_date, lottery_type, draw_period, numbers FROM lottery_draws ORDER BY id DESC LIMIT 1";
    $result = $db_connection->query($query);

    if ($row = $result->fetch_assoc()) {
        $reply_text = "最新开奖记录:\\n" .
                      "  - 类型: {$row['lottery_type']}\\n" .
                      "  - 日期: {$row['draw_date']}\\n" .
                      "  - 期号: {$row['draw_period']}\\n" .
                      "  - 号码: {$row['numbers']}";
    } else {
        $reply_text = "数据库中没有开奖记录。";
    }
    send_telegram_message($chat_id, $reply_text);
}

/**
 * Handles the /add command.
 */
function handle_add_command($chat_id, array $command_parts): void
{
    if (count($command_parts) < 4) {
        send_telegram_message($chat_id, "格式错误。用法: /add [类型] [期号] [号码]");
        return;
    }

    $data = [
        'lottery_type' => $command_parts[1],
        'draw_period'  => $command_parts[2],
        'numbers'      => $command_parts[3],
        'draw_date'    => date('Y-m-d')
    ];

    if (save_lottery_draw($data)) {
        send_telegram_message($chat_id, "成功添加开奖记录。");
    } else {
        send_telegram_message($chat_id, "添加开奖记录失败，可能该期号已存在。请检查日志。");
    }
}

/**
 * Handles the /delete command.
 */
function handle_delete_command($chat_id, array $command_parts): void
{
    if (count($command_parts) < 3) {
        send_telegram_message($chat_id, "格式错误。用法: /delete [类型] [期号]");
        return;
    }

    global $db_connection;
    $lottery_type = $command_parts[1];
    $draw_period = $command_parts[2];

    $stmt = $db_connection->prepare("DELETE FROM lottery_draws WHERE lottery_type = ? AND draw_period = ?");
    $stmt->bind_param("ss", $lottery_type, $draw_period);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        send_telegram_message($chat_id, "成功删除记录。");
    } else {
        send_telegram_message($chat_id, "未找到要删除的记录。");
    }
    $stmt->close();
}


/**
 * Handles the /finduser command.
 */
function handle_find_user_command($chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误。用法: /finduser [邮箱]");
        return;
    }

    global $db_connection;
    $search_term = $command_parts[1];

    $stmt = $db_connection->prepare("SELECT id, email, created_at FROM users WHERE email = ?");
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $reply_text = "✅ 找到用户信息:\\n" .
                      "  - 用户ID: {$user['id']}\\n" .
                      "  - 邮箱: {$user['email']}\\n" .
                      "  - 注册时间: {$user['created_at']}";
    } else {
        $reply_text = "❌ 未找到用户: " . htmlspecialchars($search_term);
    }
    $stmt->close();
    send_telegram_message($chat_id, $reply_text);
}


/**
 * Handles the /deleteuser command.
 */
function handle_delete_user_command($chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误。用法: /deleteuser [邮箱]");
        return;
    }

    global $db_connection;
    $search_term = $command_parts[1];

    $stmt_find = $db_connection->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt_find->bind_param("s", $search_term);
    $stmt_find->execute();
    $result = $stmt_find->get_result();

    if (!$user = $result->fetch_assoc()) {
        send_telegram_message($chat_id, "❌ 未找到用户: " . htmlspecialchars($search_term));
        $stmt_find->close();
        return;
    }
    $stmt_find->close();

    $user_id = $user['id'];
    $email = $user['email'];

    $db_connection->begin_transaction();
    try {
        $stmt_delete_emails = $db_connection->prepare("DELETE FROM emails WHERE user_id = ?");
        $stmt_delete_emails->bind_param("i", $user_id);
        $stmt_delete_emails->execute();
        $email_rows_affected = $stmt_delete_emails->affected_rows;
        $stmt_delete_emails->close();

        $stmt_delete_user = $db_connection->prepare("DELETE FROM users WHERE id = ?");
        $stmt_delete_user->bind_param("i", $user_id);
        $stmt_delete_user->execute();

        $db_connection->commit();
        send_telegram_message($chat_id, "✅ 成功删除用户 {$email} 及 {$email_rows_affected} 封关联邮件。");

    } catch (Exception $e) {
        $db_connection->rollback();
        send_telegram_message($chat_id, "❌ 操作失败！在删除过程中发生严重错误: " . $e->getMessage());
    }
}

/**
 * Handles setting the Gemini API key.
 */
function handle_set_gemini_key_command($chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误。用法: /setgeminikey [API密钥]");
        return;
    }

    $api_key = $command_parts[1];
    if (set_gemini_api_key($api_key)) {
        send_telegram_message($chat_id, "✅ Gemini API密钥已成功更新。");
    } else {
        send_telegram_message($chat_id, "❌ 更新Gemini API密钥失败，请检查数据库或日志。");
    }
}

/**
 * Handles a chat request with an AI service.
 */
function handle_ai_chat_command($chat_id, string $prompt, string $service): void
{
    send_telegram_message($chat_id, "正在思考中，请稍候...");

    $response = chat_with_ai($prompt, $service);

    if ($response !== null) {
        // Telegram对Markdown的解析要求特定字符被转义
        $escaped_response = str_replace(
            ['_', '*', '`', '['],
            ['\\_', '\\*', '\\`', '\\['],
            $response
        );
        send_telegram_message($chat_id, $escaped_response, null, 'Markdown');
    } else {
        $error_message = "❌ AI（{$service}）调用失败。\\n请检查：\\n1. Cloudflare凭据是否在.env中正确配置。\\n2. Gemini API密钥是否已通过Bot正确设置。\\n3. API服务本身是否可用。";
        send_telegram_message($chat_id, $error_message);
    }
}

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

    $reply_text = "✅ <b>期号 {$draw_period} 结算完成！</b>\\n\\n" .
                  "- 结算单据数: {$result['settled_count']} 张\\n" .
                  "- 总投注额: " . number_format($result['total_bets'], 2) . "\\n" .
                  "- 总派奖额: " . number_format($result['total_winnings'], 2) . "\\n" .
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

    $reply_text = "📊 <b>期号 {$draw_period} 结算报告</b>\\n\\n" .
                  "- 已结算单据: {$report['settled_count']} 张\\n" .
                  "- 总投注额: " . number_format($report['total_bets'], 2) . "\\n" .
                  "- 总派奖额: " . number_format($report['total_winnings'], 2) . "\\n" .
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
 * [MODIFIED] Generates a report for an already settled draw period。
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
