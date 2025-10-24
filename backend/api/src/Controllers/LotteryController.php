<?php
namespace App\Controllers;

use PDO;

class LotteryController {

    /**
     * Fetches the latest lottery results for each type and formats them into a single string.
     *
     * @return string A formatted string containing the latest lottery results, or an appropriate message on failure/no results.
     */
    public static function getLatestLotteryResultsFormatted(): string
    {
        try {
            $pdo = getDbConnection();

            // SQL query to get the latest entry for each lottery_type based on the most recent draw_date.
            $sql = "
                SELECT r1.*
                FROM lottery_results r1
                INNER JOIN (
                    SELECT lottery_type, MAX(draw_date) AS max_draw_date
                    FROM lottery_results
                    GROUP BY lottery_type
                ) r2 ON r1.lottery_type = r2.lottery_type AND r1.draw_date = r2.max_draw_date
                ORDER BY r1.draw_date DESC;
            ";

            $stmt = $pdo->query($sql);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($results)) {
                return '暂无开奖结果。请检查数据库中是否有数据。';
            }

            // Format the results into a string for the Telegram message using HTML for rich text.
            $message = "<b>🎉 最新开奖结果 🎉</b>\n\n";
            foreach ($results as $row) {
                $message .= "<b>✅ " . htmlspecialchars($row['lottery_type']) . " - 第 " . htmlspecialchars($row['issue_number']) . " 期</b>\n";
                $message .= "<b>开奖号码:</b> " . htmlspecialchars($row['winning_numbers']) . "\n";
                $message .= "<b>开奖时间:</b> " . htmlspecialchars($row['draw_date']) . "\n";
                $message .= "--------------------\n";
            }

            return $message;

        } catch (\Exception $e) {
            // Log the actual error for debugging purposes.
            error_log('LotteryController Error: ' . $e->getMessage());
            // Return a generic error message to the user.
            return '获取开-奖结果时发生内部错误，请联系管理员。';
        }
    }
}
