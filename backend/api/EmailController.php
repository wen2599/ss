<?php
class EmailController {
    private $db_connection;

    public function __construct() {
        global $db_connection;
        $this->db_connection = $db_connection;
    }

    public function handleRequest() {
        $data = json_decode(file_get_contents("php://input"), true);

        // --- Worker Authentication ---
        if (!isset($data['worker_secret']) || $data['worker_secret'] !== getenv('EMAIL_HANDLER_SECRET')) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Unauthorized"]);
            return;
        }

        if (isset($data['from']) && isset($data['subject']) && isset($data['body']) && isset($data['user_id'])) {
            $this->saveEmail($data);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Invalid email data. 'from', 'subject', 'body', and 'user_id' are required."]);
        }
    }

    private function saveEmail($data) {
        $user_id = $data['user_id'];
        $from = $data['from'];
        $subject = $data['subject'];
        $body = $data['body'];

        // For now, we will just save the raw body.
        // In the future, we can add the AI parsing logic here.
        $jsonExtractedData = json_encode([]);

        $stmt = $this->db_connection->prepare("INSERT INTO emails (user_id, from_address, subject, body, extracted_data) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $from, $subject, $body, $jsonExtractedData);

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(["message" => "Email saved successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to save email."]);
        }
        $stmt->close();
    }
}
