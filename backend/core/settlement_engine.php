<?php
// 文件名: settlement_engine.php
// 路径: backend/core/settlement_engine.php

// 注意: 这是一个示例结算引擎。您需要根据实际的赔率和规则进行修改。
class SettlementEngine {
    private $winning_numbers;
    private $special_number;
    private $odds; // 赔率

    public function __construct($lottery_result) {
        $this->winning_numbers = explode(',', $lottery_result['winning_numbers']);
        $this->special_number = $lottery_result['special_number'];
        
        // 在这里定义您的赔率
        $this->odds = [
            'special' => 45, // 示例赔率
            'normal' => 7,   // 示例赔率
        ];
    }

    public function settle($structured_data) {
        $total_bet = 0;
        $total_win = 0;
        $details = [];

        foreach ($structured_data as $bet) {
            $bet_amount = (float)($bet['amount'] ?? 0);
            $total_bet += $bet_amount;
            
            $win_amount = 0;
            $is_win = false;
            $description = $this->get_bet_description($bet);

            switch ($bet['type']) {
                case 'special':
                    if (isset($bet['number']) && $bet['number'] == $this->special_number) {
                        $is_win = true;
                        $win_amount = $bet_amount * $this->odds['special'];
                    }
                    break;
                case 'normal':
                    if (isset($bet['number']) && in_array($bet['number'], $this->winning_numbers)) {
                        $is_win = true;
                        $win_amount = $bet_amount * $this->odds['normal'];
                    }
                    break;
                // 在这里添加更多复杂玩法的结算逻辑，例如 'combination', 'zodiac'
            }
            
            $total_win += $win_amount;
            $details[] = [
                'description' => $description,
                'bet_amount' => $bet_amount,
                'is_win' => $is_win,
                'win_amount' => $win_amount
            ];
        }

        return [
            'total_bet' => $total_bet,
            'total_win' => $total_win,
            'profit' => $total_win - $total_bet,
            'details' => $details
        ];
    }

    private function get_bet_description($bet) {
        $type = $bet['type'] ?? '未知';
        $amount = $bet['amount'] ?? 0;
        $content = '';

        if (isset($bet['number'])) $content = $bet['number'];
        if (isset($bet['numbers'])) $content = implode(',', $bet['numbers']);
        if (isset($bet['name'])) $content = $bet['name'];
        
        return "玩法: {$type}, 内容: {$content}, 金额: {$amount}";
    }
}