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

        if (isset($data['from']) && isset($data['subject']) && isset($data['body'])) {
            $this->saveEmail($data);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid email data."]);
        }
    }

    private function saveEmail($data) {
        $from = $data['from'];
        $subject = $data['subject'];
        $body = $data['body'];

        // Look up user_id by the 'from' email address
        $stmt = $this->db_connection->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $from);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            $user_id = $user['id'];
            $stmt->close();

            $extracted_data = json_encode(parse_lottery_message($body) ?? []);

            $insert_stmt = $this->db_connection->prepare("INSERT INTO emails (user_id, from_address, subject, body, extracted_data) VALUES (?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("issss", $user_id, $from, $subject, $body, $extracted_data);

            if ($insert_stmt->execute()) {
                http_response_code(200);
                echo json_encode(["status" => "success", "message" => "Email saved successfully."]);
            } else {
                error_log("Failed to save email for user_id {$user_id}: " . $insert_stmt->error);
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Failed to save email."]);
            }
            $insert_stmt->close();
        } else {
            // If no user is found, discard the email
            http_response_code(200); // Respond with 200 OK to acknowledge receipt but do nothing
            echo json_encode(["status" => "success", "message" => "Email from unregistered user discarded."]);
            $stmt->close();
        }
    }
}
