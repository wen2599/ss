<?php
namespace App\Controllers;

use PDO;
use PDOException;
use Throwable;

class LotteryController
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Fetches lottery results.
     */
    public function getResults()
    {
        try {
            $stmt = $this->pdo->query("SELECT id, username, prize, draw_date FROM lottery_winners ORDER BY draw_date DESC");
            $winners = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(200, ['status' => 'success', 'data' => $winners]);
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            jsonError(500, 'Failed to retrieve lottery results due to a server error.');
        }
    }
}
