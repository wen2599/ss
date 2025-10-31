<?php
// backend/bot/commands/lottery_latest.php

require_once __DIR__ . '/../bot_helpers.php';

function handle_callback($bot_token, $chat_id, $callback_data) {
    $results = fetch_latest_lottery_results();

    if (empty($results)) {
        send_message($bot_token, $chat_id, "抱歉，未能获取到最新的开奖结果。请稍后再试或检查配置。");
        return;
    }

    $response_text = "<b>最新开奖结果：</b>\n\n";
    foreach ($results as $result) {
        $response_text .= "<b>期号:</b> {$result['issue_number']}\n";
        $response_text .= "<b>开奖日期:</b> {$result['draw_date']}\n";
        $response_text .= "<b>号码:</b> {$result['numbers']}\n";
        $response_text .= "--------------------\n";
    }

    send_message($bot_token, $chat_id, $response_text);
}
