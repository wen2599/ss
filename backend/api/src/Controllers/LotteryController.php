<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;
use PDOException; // Use specific PDOException

class LotteryController extends BaseController {

    /**
     * Fetches the latest lottery results from the database, grouped by lottery type.
     * This is a reusable method that can be called by other controllers or services.
     *
     * @return array An array of the latest lottery results, each as an associative array.
     * @throws PDOException If the database query fails.
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
        } catch (PDOException $e) {
            // Log the error and re-throw to be handled by the caller, using specific exception type.
            error_log('Failed to fetch lottery results data: ' . $e->getMessage());
            throw $e; // Re-throw the exception to be handled by the calling context
        }
    }

    /**
     * API endpoint to get the latest lottery results and send as a JSON response.
     * This method utilizes the reusable fetchLatestResultsData() method.
     */
    public function getLatestResults(): void
    {
        try {
            $results = $this->fetchLatestResultsData();
            $this->jsonResponse(200, ['status' => 'success', 'data' => $results]);
        } catch (PDOException $e) {
            // Delegate to unified error handler from BaseController
            $this->jsonError(500, 'Failed to fetch lottery results.', $e);
        } catch (\Throwable $e) {
            // Catch any other unexpected errors
            $this->jsonError(500, 'An unexpected error occurred while fetching lottery results.', $e);
        }
    }
}
