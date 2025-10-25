<?php
// backend/api/AuthController.php

class AuthController {
    private $db_connection;

    public function __construct($db_connection) {
        $this->db_connection = $db_connection;
    }

    public function login($data) {
        if (isset($data['email']) && isset($data['password'])) {
            $email = $data['email'];
            $password = $data['password'];

            $stmt = $this->db_connection->prepare("SELECT id, password FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    // Start session and store user ID
                    session_start();
                    $_SESSION['user_id'] = $user['id'];

                    http_response_code(200);
                    echo json_encode(["message" => "Login successful"]);
                } else {
                    http_response_code(401);
                    echo json_encode(["message" => "Invalid credentials"]);
                }
            } else {
                http_response_code(404);
                echo json_encode(["message" => "User not found"]);
            }
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Email and password are required"]);
        }
    }

    public function register($data) {
        if (isset($data['email']) && isset($data['password'])) {
            $email = $data['email'];
            $password = password_hash($data['password'], PASSWORD_BCRYPT);

            $stmt = $this->db_connection->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $email, $password);

            if ($stmt->execute()) {
                // Start session and log the user in
                session_start();
                $_SESSION['user_id'] = $stmt->insert_id;

                http_response_code(201);
                echo json_encode(["message" => "User created successfully"]);
            } else {
                http_response_code(409); // Conflict, user likely already exists
                echo json_encode(["message" => "User with this email already exists"]);
            }
            $stmt->close();
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Email and password are required"]);
        }
    }

    public function logout() {
        // Start the session
        session_start();

        // Unset all session variables
        $_SESSION = array();

        // Destroy the session
        session_destroy();

        // Send a success response
        http_response_code(200);
        echo json_encode(["message" => "Logout successful"]);
    }
}
