<?php
class EmailController {
    private $pdo;
    public function __construct() { $this->pdo = get_db_connection(); }

    public function receive($data) {
        $from = $data['from'] ?? null;
        $content = $data['content'] ?? null;
        if (!$from || !$content) { send_json_response(['error' => '缺少发件人或内容'], 400); }

        // 根据发件人邮箱找到用户 ID
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$from]);
        $user = $stmt->fetch();
        if (!$user) { send_json_response(['error' => '未找到该发件人对应的用户'], 404); }

        // 存储原始邮件
        $stmt = $this->pdo->prepare("INSERT INTO bets_raw_emails (user_id, email_content) VALUES (?, ?)");
        if ($stmt->execute([$user['id'], $content])) {
            send_json_response(['message' => '邮件已接收，等待处理'], 201);
        } else {
            send_json_response(['error' => '邮件存储失败'], 500);
        }
    }
}
