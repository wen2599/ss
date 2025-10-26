<?php

declare(strict_types=1);

// backend/handlers.php

/**
 * Handles the /help and /start command.
 */
function handle_help_command($chat_id, $reply_markup = null): void
{
    $reply_text = "您好, 管理员！可用的命令有:\n\n" .
                  "/help - 显示此帮助信息\n" .
                  "/stats - 查看系统统计数据\n" .
                  "/latest - 查询最新一条开奖记录\n" .
                  "/add [期号] [号码] - 手动添加开奖记录\n" .
                  "  (例如: /add 2023001 01,02,03,04,05)\n" .
                  "/delete [期号] - 删除一条开奖记录\n" .
                  "  (例如: /delete 2023001)";
    send_telegram_message($chat_id, $reply_text, $reply_markup);
}

/**
 * Handles the /stats command.
 */
function handle_stats_command($chat_id): void
{
    $stats = get_system_stats();
    $reply_text = "📊 系统统计数据:\n" .
                  "  - 注册用户数: {$stats['users']}\n" .
                  "  - 已保存邮件数: {$stats['emails']}\n" .
                  "  - 开奖记录数: {$stats['lottery_draws']}";
    send_telegram_message($chat_id, $reply_text);
}

/**
 * Handles the /latest command.
 */
function handle_latest_command($chat_id): void
{
    global $db_connection;
    $query = "SELECT draw_date, draw_period, numbers, created_at FROM lottery_draws ORDER BY id DESC LIMIT 1";

    if ($result = $db_connection->query($query)) {
        if ($row = $result->fetch_assoc()) {
            $reply_text = "🔍 最新开奖记录:\n" .
                          "  - 日期: {$row['draw_date']}\n" .
                          "  - 期号: {$row['draw_period']}\n" .
                          "  - 号码: {$row['numbers']}\n" .
                          "  - 记录时间: {$row['created_at']}";
        } else {
            $reply_text = "数据库中暂无开奖记录。";
        }
        $result->free();
    } else {
        $reply_text = "查询最新记录时出错。";
        error_log("DB Error in /latest: " . $db_connection->error);
    }

    send_telegram_message($chat_id, $reply_text);
}

/**
 * Handles the /add command.
 */
function handle_add_command($chat_id, array $command_parts): void
{
    if (count($command_parts) < 3) {
        send_telegram_message($chat_id, "格式错误。请使用: /add [期号] [号码]\n例如: /add 2023001 01,02,03,04,05");
        return;
    }

    $period = $command_parts[1];
    $numbers = $command_parts[2];

    if (! preg_match('/^\d+$/', $period)) {
        send_telegram_message($chat_id, "期号格式似乎不正确。应为一串数字，例如 '2023001'。");
        return;
    }
    if (! preg_match('/^(\d{1,2},)+\d{1,2}$/', $numbers)) {
        send_telegram_message($chat_id, "号码格式似乎不正确。应为以逗号分隔的数字，例如 '01,02,03'");
        return;
    }

    $data = [
        'draw_date' => date('Y-m-d'), // Use current date for manual entries
        'draw_period' => $period,
        'numbers' => $numbers,
    ];

    if (save_lottery_draw($data)) {
        send_telegram_message($chat_id, "✅ 记录已成功添加:\n  - 日期: {$data['draw_date']}\n  - 期号: {$data['draw_period']}\n  - 号码: {$data['numbers']}");
    } else {
        send_telegram_message($chat_id, "❌ 添加记录失败。可能是数据库错误或该期号已存在。");
    }
}

/**
 * Handles the /delete command.
 */
function handle_delete_command($chat_id, array $command_parts): void
{
    global $db_connection;
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误。请使用: /delete [期号]\n例如: /delete 2023001");
        return;
    }

    $period = $command_parts[1];

    if (! preg_match('/^\d+$/', $period)) {
        send_telegram_message($chat_id, "期号格式似乎不正确。应为一串数字，例如 '2023001'。");
        return;
    }

    $stmt = $db_connection->prepare("DELETE FROM lottery_draws WHERE draw_period = ?");
    if (! $stmt) {
        error_log("DB Prepare Error in /delete: " . $db_connection->error);
        send_telegram_message($chat_id, "❌ 删除记录时发生数据库错误。");
        return;
    }

    $stmt->bind_param("s", $period);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_telegram_message($chat_id, "✅ 已成功删除期号为 {$period} 的记录。");
        } else {
            send_telegram_message($chat_id, "🤷 未找到期号为 {$period} 的记录。");
        }
    } else {
        error_log("DB Execute Error in /delete: " . $stmt->error);
        send_telegram_message($chat_id, "❌ 执行删除操作时出错。");
    }

    $stmt->close();
}
