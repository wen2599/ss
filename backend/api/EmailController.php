<?php
require_once __DIR__ . '/../helpers.php'; // Ensure helpers.php is included for parse_lottery_message

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
            echo json_encode(["message" => "Invalid email data."]);
        }
    }

    private function saveEmail($data) {
        $user_id = $data['user_id'];
        $from = $data['from'];
        $subject = $data['subject'];
        $body = $data['body'];

        $extracted_data = null;
        // Attempt to parse lottery message from the email body
        $parsed_lottery_data = parse_lottery_message($body);
        if ($parsed_lottery_data) {
            $extracted_data = json_encode($parsed_lottery_data);
        }

        // If no lottery data was extracted, save an empty JSON object
        if ($extracted_data === null) {
            $extracted_data = json_encode([]);
        }

        $stmt = $this->db_connection->prepare("INSERT INTO emails (user_id, from_address, subject, body, extracted_data) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $from, $subject, $body, $extracted_data);

        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(["message" => "Email saved successfully."]);
        } else {
            error_log("Failed to save email for user_id {$user_id}: " . $stmt->error);
            http_response_code(500);
            echo json_encode(["message" => "Failed to save email."]);
        }
        $stmt->close();
    }
}
