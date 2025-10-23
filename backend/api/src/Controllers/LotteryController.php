<?php
declare(strict_types=1);

namespace App\Controllers;

class LotteryController extends BaseController
{
    public function getResults(): void
    {
        try {
            $pdo = getDbConnection();
            $sql = "
                WITH ranked_results AS (
                    SELECT
                        *,
                        ROW_NUMBER() OVER(PARTITION BY lottery_type ORDER BY draw_date DESC) as rn
                    FROM
                        lottery_results
                )
                SELECT
                    id, lottery_type, issue_number, winning_numbers, number_colors_json, draw_date
                FROM
                    ranked_results
                WHERE
                    rn = 1
                ORDER BY
                    draw_date DESC";
            $stmt = $pdo->query($sql);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->jsonResponse(200, ['status' => 'success', 'data' => $results]);
        } catch (\PDOException $e) {
            error_log("Lottery Results DB Error: " . $e->getMessage());
            $this->jsonError(500, 'Database error while fetching lottery results.');
        }
    }
}
