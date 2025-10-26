<?php
class UserController {
    private $db_connection;

    public function __construct() {
        global $db_connection;
        $this->db_connection = $db_connection;
    }

    public function isRegistered() {
        // --- Worker Authentication ---
        $worker_secret = $_GET['worker_secret'] ?? '';
        if ($worker_secret !== getenv('EMAIL_HANDLER_SECRET')) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Unauthorized"]);
            return;
        }

        $email = $_GET['email'] ?? '';
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Email is required"]);
            return;
        }

        $stmt = $this->db_connection->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            echo json_encode([
                "status" => "success",
                "data" => [
                    "is_registered" => true,
                    "user_id" => $user['id']
                ]
            ]);
        } else {
            echo json_encode([
                "status" => "success",
                "data" => [
                    "is_registered" => false,
                    "user_id" => null
                ]
            ]);
        }
        $stmt->close();
    }
}
