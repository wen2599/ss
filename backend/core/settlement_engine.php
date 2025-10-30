<?php
/**
 * 文件名: settlement_engine.php
 * 路径: core/settlement_engine.php
 * 描述: 彩票结算引擎。
 * 
 * 注意: 这是一个示例实现。您需要根据您的具体玩法和赔率来调整结算逻辑。
 */

class SettlementEngine {
    private $winning_numbers; // 平码数组
    private $special_number;  // 特码
    private $odds;            // 赔率配置

    /**
     * 构造函数
     * @param array $lottery_result 从数据库查询出的开奖结果记录。
     *        例如: ['winning_numbers' => '01,02,03,04,05,06', 'special_number' => '07']
     */
    public function __construct($lottery_result) {
        // 确保 winning_numbers 是一个数组
        $this->winning_numbers = isset($lottery_result['winning_numbers']) ? explode(',', $lottery_result['winning_numbers']) : [];
        $this->special_number = $lottery_result['special_number'] ?? null;
        
        // --- 在这里定义您的赔率 ---
        // 这是一个示例，请根据您的实际情况修改
        $this->odds = [
            'special' => 45,     // 特码赔率
            'normal'  => 7,      // 平码赔率
            'zodiac'  => 11,     // 生肖赔率
            'combo_2_of_3' => 20, // 三中二赔率 (示例)
            'combo_2_of_2' => 50, // 二全中赔率 (示例)
        ];
    }

    /**
     * 对一个结构化的投注单进行结算
     * @param array $structured_bets AI 从邮件中提取的投注项数组。
     * @return array 包含总计和详细结果的结算报告。
     */
    public function settle(array $structured_bets) {
        $total_bet_amount = 0;
        $total_win_amount = 0;
        $settlement_details = [];

        foreach ($structured_bets as $bet) {
            $bet_amount = (float)($bet['amount'] ?? 0);
            $total_bet_amount += $bet_amount;
            
            $win_amount = 0;
            $is_win = false;
            $description = $this->get_bet_description($bet);

            // 根据投注类型调用不同的结算方法
            switch (strtolower($bet['type'] ?? '')) {
                case 'special': // 特码
                    if (isset($bet['number']) && $bet['number'] == $this->special_number) {
                        $is_win = true;
                        $win_amount = $bet_amount * $this->odds['special'];
                    }
                    break;
                    
                case 'normal': // 平码
                    if (isset($bet['number']) && in_array($bet['number'], $this->winning_numbers)) {
                        $is_win = true;
                        $win_amount = $bet_amount * $this->odds['normal'];
                    }
                    break;

                case 'combination': // 连码
                    // 这里需要更复杂的逻辑，例如
                    // if ($bet['subtype'] === '三中二') { ... }
                    break;

                case 'zodiac': // 生肖
                    // 需要一个号码到生肖的映射关系来判断
                    // if ($this->is_zodiac_win($bet['name'])) { ... }
                    break;
            }
            
            $total_win_amount += $win_amount;
            $settlement_details[] = [
                'description' => $description,
                'bet_amount'  => $bet_amount,
                'is_win'      => $is_win,
                'win_amount'  => $win_amount
            ];
        }

        return [
            'total_bet_amount'  => $total_bet_amount,
            'total_win_amount'  => $total_win_amount,
            'profit'            => $total_win_amount - $total_bet_amount,
            'details'           => $settlement_details
        ];
    }

    /**
     * 生成一个可读的投注项描述
     * @param array $bet 单个投注项。
     * @return string 描述文本。
     */
    private function get_bet_description(array $bet) {
        $type = $bet['type'] ?? '未知';
        $amount = $bet['amount'] ?? 0;
        $content = '';

        if (isset($bet['subtype'])) $type .= " ({$bet['subtype']})";
        if (isset($bet['number'])) $content = $bet['number'];
        if (isset($bet['numbers']) && is_array($bet['numbers'])) $content = implode(',', $bet['numbers']);
        if (isset($bet['name'])) $content = $bet['name'];
        
        return "玩法: {$type}, 内容: {$content}, 金额: {$amount}";
    }

    // 您可以在这里添加更多辅助方法，例如:
    // private function is_zodiac_win($zodiac_name) { ... }
    // private function calculate_combination_win($subtype, $numbers) { ... }
}