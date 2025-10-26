<?php

declare(strict_types=1);

// backend/handlers.php

/**
 * Handles the /help and /start command.
 */
function handle_help_command($chat_id): void
{
    $reply_text = "您好, 管理员！请使用下面的菜单进行操作，或直接输入命令:\n\n" .
                  "/help - 显示此帮助信息\n" .
                  "/stats - 查看系统统计数据\n" .
                  "/latest - 查询最新一条开奖记录\n" .
                  "/add [类型] [期号] [号码] - 手动添加开奖记录\n" .
                  "/delete [类型] [期号] - 删除一条开奖记录";

    $keyboard = [
        'keyboard' => [
            [['text' => '/latest'], ['text' => '/stats']],
            [['text' => '/help']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false, // Set to false to make it a persistent menu
        'selective' => true
    ];

    $reply_markup = json_encode($keyboard);
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
    $query = "SELECT draw_date, lottery_type, draw_period, numbers FROM lottery_draws ORDER BY id DESC LIMIT 1";
    $result = $db_connection->query($query);

    if ($row = $result->fetch_assoc()) {
        $reply_text = "最新开奖记录:\n" .
                      "  - 类型: {$row['lottery_type']}\n" .
                      "  - 日期: {$row['draw_date']}\n" .
                      "  - 期号: {$row['draw_period']}\n" .
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
        send_telegram_message($chat_id, "格式错误。用法: /add [类型] [期号] [号码]\n例如: /add 香港六合彩 2023001 01,02,03,04,05,06,07");
        return;
    }

    $data = [
        'lottery_type' => $command_parts[1],
        'draw_period'  => $command_parts[2],
        'numbers'      => $command_parts[3],
        'draw_date'    => date('Y-m-d')
    ];

    if (save_lottery_draw($data)) {
        send_telegram_message($chat_id, "成功添加开奖记录:\n类型: {$data['lottery_type']}\n期号: {$data['draw_period']}");
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
        send_telegram_message($chat_id, "格式错误。用法: /delete [类型] [期号]\n例如: /delete 香港六合彩 2023001");
        return;
    }

    global $db_connection;
    $lottery_type = $command_parts[1];
    $draw_period = $command_parts[2];

    $stmt = $db_connection->prepare("DELETE FROM lottery_draws WHERE lottery_type = ? AND draw_period = ?");
    $stmt->bind_param("ss", $lottery_type, $draw_period);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            send_telegram_message($chat_id, "成功删除期号为 {$draw_period} ({$lottery_type}) 的开奖记录。");
        } else {
            send_telegram_message($chat_id, "未找到期号为 {$draw_period} ({$lottery_type}) 的开奖记录。");
        }
    } else {
        send_telegram_message($chat_id, "删除失败: " . $stmt->error);
    }
    $stmt->close();
}
