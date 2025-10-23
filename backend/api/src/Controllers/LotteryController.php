<?php
declare(strict_types=1);

namespace App\Controllers;

class LotteryController extends BaseController
{
    public function getResults(): void
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT `username`, `prize`, `draw_date` FROM `lottery_winners` ORDER BY `draw_date` DESC");
            $winners = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->jsonResponse(200, ['status' => 'success', 'data' => $winners]);
        } catch (\PDOException $e) {
            error_log("Lottery Results DB Error: " . $e->getMessage());
            $this->jsonError(500, 'Database error while fetching lottery results.');
        }
    }

    public function getWinners(): void
    {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->query("SELECT id, username, prize, draw_date FROM lottery_winners ORDER BY draw_date DESC");
            $winners = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->jsonResponse(200, ['status' => 'success', 'data' => $winners]);
        } catch (\PDOException $e) {
            error_log("Lottery Winners DB Error: " . $e->getMessage());
            $this->jsonError(500, 'Database error while fetching lottery winners.');
        }
    }
}
