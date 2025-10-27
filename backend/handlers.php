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
                  "/delete [类型] [期号] - 删除一条开奖记录\n" .
                  "/finduser [用户名/邮箱] - 查找用户信息\n" .
                  "/deleteuser [用户名/邮箱] - 删除用户及其数据";

    $keyboard = [
        'keyboard' => [
            [['text' => '最新开奖'], ['text' => '系统统计']],
            [['text' => '手动添加'], ['text' => '删除记录']],
            [['text' => '查找用户'], ['text' => '删除用户']],
            [['text' => '帮助说明']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
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


/**
 * Handles the /finduser command.
 */
function handle_find_user_command($chat_id, array $command_parts): void
{
    if (count($command_parts) < 2) {
        send_telegram_message($chat_id, "格式错误。用法: /finduser [用户名或邮箱]");
        return;
    }
    
    global $db_connection;
    $search_term = $command_parts[1];

    $stmt = $db_connection->prepare("SELECT id, username, email, created_at FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $reply_text = "✅ 找到用户信息:\n" .
                      "  - 用户ID: {$user['id']}\n" .
                      "  - 用户名: {$user['username']}\n" .
                      "  - 邮箱: {$user['email']}\n" .
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
        send_telegram_message($chat_id, "格式错误。用法: /deleteuser [用户名或邮箱]");
        return;
    }

    global $db_connection;
    $search_term = $command_parts[1];

    // 1. Find the user to get their ID and details
    $stmt_find = $db_connection->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ?");
    $stmt_find->bind_param("ss", $search_term, $search_term);
    $stmt_find->execute();
    $result = $stmt_find->get_result();
    
    if (!$user = $result->fetch_assoc()) {
        send_telegram_message($chat_id, "❌ 未找到用户: " . htmlspecialchars($search_term));
        $stmt_find->close();
        return;
    }
    $stmt_find->close();
    
    $user_id = $user['id'];
    $username = $user['username'];
    $email = $user['email'];

    // 2. Use a transaction to delete the user and their emails
    $db_connection->begin_transaction();
    try {
        // Delete related emails first
        $stmt_delete_emails = $db_connection->prepare("DELETE FROM emails WHERE user_id = ?");
        $stmt_delete_emails->bind_param("i", $user_id);
        $stmt_delete_emails->execute();
        $email_rows_affected = $stmt_delete_emails->affected_rows;
        $stmt_delete_emails->close();

        // Then delete the user
        $stmt_delete_user = $db_connection->prepare("DELETE FROM users WHERE id = ?");
        $stmt_delete_user->bind_param("i", $user_id);
        $stmt_delete_user->execute();
        $user_rows_affected = $stmt_delete_user->affected_rows;
        $stmt_delete_user->close();

        if ($user_rows_affected > 0) {
            $db_connection->commit();
            send_telegram_message($chat_id, "✅ 成功删除用户 {$username} ({$email}) 及 {$email_rows_affected} 封关联邮件。");
        } else {
            // This case should theoretically not be reached if the user was found
            $db_connection->rollback();
            send_telegram_message($chat_id, "⚠️ 删除用户失败，但该用户存在。请检查数据库。");
        }
    } catch (Exception $e) {
        $db_connection->rollback();
        send_telegram_message($chat_id, "❌ 操作失败！在删除过程中发生严重错误: " . $e->getMessage());
        error_log("Bot Error: Failed to delete user {$username}: " . $e->getMessage());
    }
}
