<?php
// backend/src/controllers/UserController.php

class UserController {
    private $conn;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    public function handleGetNumbers() {
        $sql = "SELECT id, number, description, created_at FROM numbers ORDER BY created_at DESC";
        $result = $this->conn->query($sql);

        if ($result) {
            $numbers = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $numbers]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database query failed.']);
        }
    }

    public function handleRegisterUser($data) {
        // Logic from api/register.php
        $email = $data['email'] ?? null;

        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Email is required.']);
            return;
        }

        $stmt = $this->conn->prepare("INSERT INTO users (email) VALUES (?)");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'User registered.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to register user.']);
        }
        $stmt->close();
    }
}
?>