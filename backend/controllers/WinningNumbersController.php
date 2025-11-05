<?php
class WinningNumbersController {
    private $pdo;
    public function __construct() { $this->pdo = get_db_connection(); }

    public function add($data) {
        $issue = $data['issue_number'] ?? null;
        $numbers = $data['numbers'] ?? null;
        $date = $data['draw_date'] ?? date('Y-m-d');
        if (!$issue || !$numbers) { send_json_response(['error' => '缺少期号或号码'], 400); }

        $stmt = $this->pdo->prepare("INSERT INTO winning_numbers (issue_number, numbers, draw_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE numbers=VALUES(numbers), draw_date=VALUES(draw_date)");
        if ($stmt->execute([$issue, $numbers, $date])) {
            send_json_response(['message' => '开奖号码已添加'], 201);
        } else {
            send_json_response(['error' => '添加失败'], 500);
        }
    }
    
    public function getAll() {
        $stmt = $this->pdo->query("SELECT issue_number, numbers, draw_date FROM winning_numbers ORDER BY draw_date DESC, issue_number DESC LIMIT 100");
        $numbers = $stmt->fetchAll();
        send_json_response($numbers);
    }
}
