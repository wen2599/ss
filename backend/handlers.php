<?php

declare(strict_types=1);

// backend/handlers.php
// 此文件包含了所有Telegram Bot的命令处理器

require_once __DIR__ . '/settlement_rules.php';

/**
 * 处理 /start 和 /help 命令
 * 发送欢迎信息和命令菜单
 * @param int $chat_id 用户会话ID
 */
function handle_help_command(int $chat_id): void
{
    $reply_text = "您好, 管理员！请使用下面的菜单进行操作或直接输入命令：\n\n" .
                  "<b>--- 核心业务 ---</b>\n" .
                  "/settle [期号] - 对指定期号进行结算\n" .
                  "/report [期号] - 获取指定期号的结算报告\n" .
                  "/latest - 查询最新一期的开奖记录\n" .
                  "/add [类型] [期号] [号码] - 手动添加开奖记录\n" .
                  "/delete [类型] [期号] - 删除指定的开奖记录\n\n" .
                  "<b>--- 用户管理 ---</b>\n" .
                  "/stats - 查看系统关键数据统计\n" .
                  "/finduser [关键词] - 根据用户名或邮箱查找用户\n" .
                  "/deleteuser [关键词] - 删除用户及其所有相关数据\n\n" .
                  "<b>--- AI 助手 ---</b>\n" .
                  "/setgeminikey [密钥] - 设置Gemini API Key\n" .
                  "/cfchat [问题] - 与Cloudflare AI进行对话\n" .
                  "/geminichat [问题] - 与Gemini AI进行对话\n" .
                  "/help - 显示此帮助信息";

    // 定义Telegram机器人的自定义键盘
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
 * 处理 /stats 命令
 * 显示系统统计数据，如用户数、邮件数等
 * @param int $chat_id 用户会话ID
 */
function handle_stats_command(int $chat_id): void
{
    $stats = get_system_stats();
    $reply_text = "📊 <b>系统统计数据:</b>\n\n" .
                  "  - 注册用户数: {$stats['users']}\n" .
                  "  - 已保存邮件数: {$stats['emails']}\n" .
                  "  - 开奖记录数: {$stats['lottery_draws']}";
    send_telegram_message($chat_id, $reply_text, null, 'HTML');
}

/**
 * 处理 /latest 命令
 * 查询并显示最新一期的开奖记录
 * @param int $chat_id 用户会话ID
 */
function handle_latest_command(int $chat_id): void
{
    global $db_connection;
    $query = "SELECT draw_date, lottery_type, draw_period, numbers FROM lottery_draws ORDER BY id DESC LIMIT 1";
    $result = $db_connection->query($query);

    if ($row = $result->fetch_assoc()) {
        $reply_text = "<b>最新开奖记录:</b>\n\n" .
                      "  - <b>类型:</b> {$row['lottery_type']}\n" .
                      "  - <b>日期:</b> {$row['draw_date']}\n" .
                      "  - <b>期号:</b> {$row['draw_period']}\n" .
                      "  - <b>号码:</b> {$row['numbers']}";
    } else {
        $reply_text = "数据库中暂无开奖记录。";
    }
    send_telegram_message($chat_id, $reply_text, null, 'HTML');
}

/**
 * 处理 /add 命令
 * 手动添加一条开奖记录
 * @param int $chat_id 用户会话ID
 * @param array $command_parts 命令参数
 */
function handle_add_command(int $chat_id, array $command_parts): void
{
    if (count($command_parts) < 4) {
        send_telegram_message($chat_id, "格式错误！\n正确用法: `/add [类型] [期号] [号码]`\n例如: `/add ssc 240325001 1,2,3,4,5`", null, 'Markdown');
        return;
    }

    $data = [
        'lottery_type' => $command_parts[1],
        'draw_period'  => $command_parts[2],
        'numbers'      => $command_parts[3],
        'draw_date'    => date('Y-m-d')
    ];

    if (save_lottery_draw($data)) {
        send_telegram_message($chat_id, "✅ 成功添加开奖记录。");
    } else {
        send_telegram_message($chat_id, "❌ 添加失败，该期号可能已存在。请检查后重试。");
    }
}

/**
 * 处理 /delete 命令
 * 删除一条开奖记录
 * @param int $chat_id 用户会话ID
 * @param array $command_parts 命令参数
 */
function handle_delete_command(int $chat_id, array $command_parts): void
{
    if (count($command_parts) < 3) {
        send_telegram_message($chat_id, "格式错误！\n正确用法: `/delete [类型] [期号]`\n例如: `/delete ssc 240325001`", null, 'Markdown');
        return;
    }

    global $db_connection;
    $lottery_type = $command_parts[1];
    $draw_period = $command_parts[2];

    $stmt = $db_connection->prepare("DELETE FROM lottery_draws WHERE lottery_type = ? AND draw_period = ?");
    $stmt->bind_param("ss", $lottery_type, $draw_period);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        send_telegram_message($chat_id, "✅ 成功删除记录。");
    } else {
        send_telegram_message($chat_id, "❌ 未找到要删除的记录，请检查类型和期号是否正确。");
    }
    $stmt->close();
}


/**
 * 处理 /finduser 命令
 * 根据用户名或邮箱查找用户
 * @param int $chat_id 用户会话ID
 * @param array $command_parts 命令参数
 */
function handle_find_user_command(int $chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误！\n正确用法: `/finduser [用户名或邮箱]`", null, 'Markdown');
        return;
    }

    global $db_connection;
    $search_term = $command_parts[1];

    $stmt = $db_connection->prepare("SELECT id, username, email, created_at FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $reply_text = "✅ <b>找到用户信息:</b>\n\n" .
                      "  - <b>用户ID:</b> {$user['id']}\n" .
                      "  - <b>用户名:</b> " . htmlspecialchars($user['username']) . "\n" .
                      "  - <b>邮箱:</b> " . htmlspecialchars($user['email']) . "\n" .
                      "  - <b>注册时间:</b> {$user['created_at']}";
    } else {
        $reply_text = "❌ 未找到用户: `" . htmlspecialchars($search_term) . "`";
    }
    $stmt->close();
    send_telegram_message($chat_id, $reply_text, null, 'HTML');
}


/**
 * 处理 /deleteuser 命令
 * 删除用户及其所有相关数据（邮件、结算记录等）
 * @param int $chat_id 用户会话ID
 * @param array $command_parts 命令参数
 */
function handle_delete_user_command(int $chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误！\n正确用法: `/deleteuser [用户名或邮箱]`", null, 'Markdown');
        return;
    }

    global $db_connection;
    $search_term = $command_parts[1];

    // 查找用户是否存在
    $stmt_find = $db_connection->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
    $stmt_find->bind_param("ss", $search_term, $search_term);
    $stmt_find->execute();
    $result = $stmt_find->get_result();

    if (!$user = $result->fetch_assoc()) {
        send_telegram_message($chat_id, "❌ 未找到用户: `" . htmlspecialchars($search_term) . "`");
        $stmt_find->close();
        return;
    }
    $stmt_find->close();

    $user_id = $user['id'];
    $username = $user['username'];
    
    // 为了安全，我们在这里可以增加一步确认操作，但当前暂不实现
    // send_telegram_message($chat_id, "您确定要删除用户 {$username} 吗？这将移除所有相关数据。请在1分钟内回复 'yes' 确认。");
    // ... 此处需要更复杂的会话管理逻辑 ...

    $db_connection->begin_transaction();
    try {
        // 删除与用户关联的邮件
        $stmt_delete_emails = $db_connection->prepare("DELETE FROM emails WHERE user_id = ?");
        $stmt_delete_emails->bind_param("i", $user_id);
        $stmt_delete_emails->execute();
        $email_rows_affected = $stmt_delete_emails->affected_rows;
        $stmt_delete_emails->close();

        // 删除用户本身
        $stmt_delete_user = $db_connection->prepare("DELETE FROM users WHERE id = ?");
        $stmt_delete_user->bind_param("i", $user_id);
        $stmt_delete_user->execute();

        $db_connection->commit();
        send_telegram_message($chat_id, "✅ 成功删除用户 `".htmlspecialchars($username)."` 及其 {$email_rows_affected} 封关联邮件。");

    } catch (Exception $e) {
        $db_connection->rollback();
        send_telegram_message($chat_id, "❌ 操作失败！在删除过程中发生严重错误: " . $e->getMessage());
    }
}


/**
 * 处理 /setgeminikey 命令
 * 设置用于与Gemini AI交互的API密钥
 * @param int $chat_id 用户会话ID
 * @param array $command_parts 命令参数
 */
function handle_set_gemini_key_command(int $chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误！\n正确用法: `/setgeminikey [您的API密钥]`", null, 'Markdown');
        return;
    }

    $api_key = $command_parts[1];
    if (set_gemini_api_key($api_key)) {
        send_telegram_message($chat_id, "✅ Gemini API密钥已成功更新。");
    } else {
        send_telegram_message($chat_id, "❌ 更新Gemini API密钥失败，请检查数据库连接或相关日志。");
    }
}

/**
 * 处理与AI服务的聊天命令 (cfchat, geminichat)
 * @param int $chat_id 用户会话ID
 * @param string $prompt 用户输入的问题
 * @param string $service 使用的AI服务 ('cloudflare' 或 'gemini')
 */
function handle_ai_chat_command(int $chat_id, string $prompt, string $service): void
{
    send_telegram_message($chat_id, "⏳ 正在思考中，请稍候...");

    $response = chat_with_ai($prompt, $service);

    if ($response !== null) {
        // Telegram对Markdown的解析要求特定字符被转义
        $escaped_response = str_replace(
            ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
            ['\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'],
            $response
        );
        send_telegram_message($chat_id, $escaped_response, null, 'MarkdownV2');
    } else {
        $error_message = "❌ AI（{$service}）调用失败。\n请检查：\n1. Cloudflare凭据是否在.env中正确配置。\n2. Gemini API密钥是否已通过Bot正确设置。\n3. 确认AI服务本身是否可用。";
        send_telegram_message($chat_id, $error_message);
    }
}

/**
 * 处理 /settle 命令
 * 对指定期号的所有待结算单据进行结算
 * @param int $chat_id 用户会话ID
 * @param array $command_parts 命令参数
 */
function handle_settle_command(int $chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误！\n正确用法: `/settle [期号]`", null, 'Markdown');
        return;
    }
    $draw_period = $command_parts[1];

    send_telegram_message($chat_id, "收到请求！正在开始为期号 `{$draw_period}` 进行结算...", null, 'Markdown');

    $result = process_settlements_for_draw($draw_period);

    if ($result === null) {
        send_telegram_message($chat_id, "❌ 结算失败: 未能找到期号为 `{$draw_period}` 的开奖记录。请先添加该期的开奖号码。");
        return;
    }

    if ($result['settled_count'] === 0) {
        send_telegram_message($chat_id, "ℹ️ 期号 `{$draw_period}` 没有找到待结算的单据。");
        return;
    }

    $net_profit = $result['total_bets'] - $result['total_winnings'];
    $profit_emoji = $net_profit >= 0 ? '🟢' : '🔴';

    $reply_text = "✅ <b>期号 {$draw_period} 结算完成！</b>\n\n" .
                  "  - 结算单据数: {$result['settled_count']} 张\n" .
                  "  - 总投注额: " . number_format($result['total_bets'], 2) . "\n" .
                  "  - 总派奖额: " . number_format($result['total_winnings'], 2) . "\n" .
                  "  - {$profit_emoji} 本期利润: " . number_format($net_profit, 2);

    send_telegram_message($chat_id, $reply_text, null, 'HTML');
}

/**
 * 处理 /report 命令
 * 生成并显示指定期号的结算报告
 * @param int $chat_id 用户会话ID
 * @param array $command_parts 命令参数
 */
function handle_report_command(int $chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误！\n正确用法: `/report [期号]`", null, 'Markdown');
        return;
    }
    $draw_period = $command_parts[1];

    $report = generate_settlement_report($draw_period);

    if ($report === null) {
        send_telegram_message($chat_id, "❌ 未能生成报告: 未找到任何与期号 `{$draw_period}` 相关的已结算单据。");
        return;
    }

    $net_profit = $report['total_bets'] - $report['total_winnings'];
    $profit_emoji = $net_profit >= 0 ? '🟢' : '🔴';

    $reply_text = "📊 <b>期号 {$draw_period} 结算报告</b>\n\n" .
                  "  - 已结算单据: {$report['settled_count']} 张\n" .
                  "  - 总投注额: " . number_format($report['total_bets'], 2) . "\n" .
                  "  - 总派奖额: " . number_format($report['total_winnings'], 2) . "\n" .
                  "  - {$profit_emoji} 本期利润: " . number_format($net_profit, 2);

    send_telegram_message($chat_id, $reply_text, null, 'HTML');
}

// --- 结算相关的辅助函数 ---

/**
 * 核心结算处理逻辑
 * @param string $draw_period 期号
 * @return array|null 结算结果或在找不到开奖记录时返回null
 */
function process_settlements_for_draw(string $draw_period): ?array
{
    global $db_connection;

    // 1. 获取指定期号的中奖号码
    $stmt_draw = $db_connection->prepare("SELECT numbers FROM lottery_draws WHERE draw_period = ?");
    $stmt_draw->bind_param("s", $draw_period);
    $stmt_draw->execute();
    $result_draw = $stmt_draw->get_result();
    if (!($draw = $result_draw->fetch_assoc())) {
        $stmt_draw->close();
        return null; // 未找到开奖记录
    }
    $winning_numbers = array_map('intval', explode(',', $draw['numbers']));
    $stmt_draw->close();

    // 2. 获取所有与该期号相关的“待结算”单据
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

            // 遍历每张单据中的每一条投注
            foreach ($bets as &$bet) { // 使用引用传递，以便直接修改数组
                $winnings = calculate_winnings($bet, $winning_numbers);
                $bet['winnings'] = $winnings;
                $bet['status'] = $winnings > 0 ? '中奖' : '未中奖'; // 更新投注状态
                $total_winnings_single += $winnings;
                $total_bets_all += floatval($bet['amount']);
            }
            unset($bet); // 及时取消引用

            $total_winnings_all += $total_winnings_single;

            // 更新数据库中的结算记录
            $stmt_update = $db_connection->prepare("UPDATE settlements SET total_winnings = ?, settlement_data = ?, status = 'settled' WHERE id = ?");
            $updated_data_json = json_encode($bets);
            $stmt_update->bind_param("dsi", $total_winnings_single, $updated_data_json, $settlement_id);
            $stmt_update->execute();
            $stmt_update->close();
        }
        $db_connection->commit();
    } catch (Exception $e) {
        $db_connection->rollback();
        error_log("Settlement failed for draw {$draw_period}: " . $e->getMessage());
        return null;
    }

    return [
        'settled_count' => count($pending_settlements),
        'total_bets' => $total_bets_all,
        'total_winnings' => $total_winnings_all
    ];
}

/**
 * 为已结算的期号生成报告
 * @param string $draw_period 期号
 * @return array|null 报告数据或在找不到记录时返回null
 */
function generate_settlement_report(string $draw_period): ?array
{
    global $db_connection;
    $stmt = $db_connection->prepare("SELECT COUNT(id) as settled_count, SUM(total_amount) as total_bets, SUM(total_winnings) as total_winnings FROM settlements WHERE draw_period = ? AND status = 'settled'");
    $stmt->bind_param("s", $draw_period);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 如果没有找到记录或计数为0，则返回null
    if (empty($result) || $result['settled_count'] == 0) {
        return null;
    }

    return [
        'settled_count' => (int)$result['settled_count'],
        'total_bets' => (float)$result['total_bets'],
        'total_winnings' => (float)$result['total_winnings']
    ];
}
