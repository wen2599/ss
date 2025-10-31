<?php
// backend/bot/commands/start.php

require_once __DIR__ . '/../bot_helpers.php';

function handle_command($bot_token, $chat_id, $message) {
    $text = "欢迎使用！请选择一个选项：";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '查询最新开奖', 'callback_data' => 'lottery_latest'],
                ['text' => '查看我的邮箱', 'callback_data' => 'view_emails']
            ],
            [
                ['text' => '帮助与说明', 'callback_data' => 'help']
            ]
        ]
    ];
    
    $reply_markup = json_encode($keyboard);
    
    send_message($bot_token, $chat_id, $text, $reply_markup);
}
