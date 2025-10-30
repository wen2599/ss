<?php
// backend/src/controllers/EmailController.php

class EmailController {
    private $conn;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    public function handleGetEmails($id = null) {
        if ($id) {
            $this->handle_get_email_by_id($id);
        } else {
            $this->handle_get_emails_list();
        }
    }

    private function handle_get_emails_list() {
        $sql = "SELECT id, sender, subject, created_at FROM emails ORDER BY created_at DESC";
        $result = $this->conn->query($sql);

        if ($result) {
            $emails = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $emails]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database query failed.']);
        }
    }

    private function handle_get_email_by_id($id) {
        $stmt = $this->conn->prepare("SELECT * FROM emails WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $email = $result->fetch_assoc();
            echo json_encode(['status' => 'success', 'data' => $email]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Email not found.']);
        }
        $stmt->close();
    }

    public function handlePostEmail($data) {
        // Logic from routes/post_email.php
        // This assumes $data is the decoded JSON body
        $raw_content = $data['raw_content'] ?? '';
        // Additional logic to parse sender, subject, etc., from raw_content if needed

        // For now, let's assume we're just saving the raw content
        $stmt = $this->conn->prepare("INSERT INTO emails (raw_content) VALUES (?)");
        $stmt->bind_param("s", $raw_content);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Email saved.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to save email.']);
        }
        $stmt->close();
    }
}
?>