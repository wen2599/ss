<?php
namespace App\Controllers;

use PDO;
use Exception;

class LotteryController extends BaseController {

    /**
     * Fetches the latest lottery results from the database.
     * This is a reusable method that can be called by other controllers.
     *
     * @return array An array of lottery results.
     * @throws Exception if the database query fails.
     */
    public function fetchLatestResultsData(): array
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Log the error and re-throw to be handled by the caller.
            error_log('Failed to fetch lottery results data: ' . $e->getMessage());
            throw $e; // Re-throw the exception to be handled by the calling context
        }
    }

    /**
     * API endpoint to get the latest lottery results and send as a JSON response.
     * This method now uses the reusable fetchLatestResultsData() method.
     */
    public function getLatestResults(): void
    {
        try {
            $results = $this->fetchLatestResultsData();
            $this->jsonResponse(200, ['status' => 'success', 'data' => $results]);
        } catch (Exception $e) {
            $this->jsonResponse(500, ['status' => 'error', 'message' => 'Failed to fetch lottery results.']);
        }
    }
}
