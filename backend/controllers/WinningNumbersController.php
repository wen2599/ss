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
        // --- 关键改动在这里 ---
        // 从 GET 请求中获取 limit 参数，如果没有则默认为 100
        // (int) 用于确保它是一个整数，防止SQL注入
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

        // 限制最大查询数量，防止滥用
        if ($limit > 200) {
            $limit = 200;
        }

        // 在 SQL 查询中使用 LIMIT
        $stmt = $this->pdo->prepare("SELECT issue_number, numbers, draw_date FROM winning_numbers ORDER BY draw_date DESC, issue_number DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $numbers = $stmt->fetchAll();
        send_json_response($numbers);
    }
}
