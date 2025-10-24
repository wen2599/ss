<?php
namespace App\Controllers;

use PDO;

class LotteryController extends BaseController {

    public function getLatestResults(): void
    {
        try {
            $pdo = $this->getDbConnection();
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
            $this->jsonResponse(200, ['status' => 'success', 'data' => $results]);
        } catch (\Exception $e) {
            error_log('LotteryController Error: ' . $e->getMessage());
            $this->jsonResponse(500, ['status' => 'error', 'message' => 'Failed to fetch lottery results.']);
        }
    }
}
