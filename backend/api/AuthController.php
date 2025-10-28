<?php
class AuthController {
    private $db_connection;

    public function __construct($db_connection) {
        $this->db_connection = $db_connection;
    }

    private function _sendJsonResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function handleRequest($data) {
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'login':
                $this->login($data);
                break;
            case 'register':
                $this->register($data);
                break;
            case 'logout':
                $this->logout();
                break;
            case 'check_session': // Added to handle session check via AuthController if needed
                $this->check_session();
                break;
            default:
                $this->_sendJsonResponse(400, ["message" => "Invalid action"]);
                break;
        }
    }

    private function login($data) {
        if (!isset($data['email']) || !isset($data['password'])) {
            $this->_sendJsonResponse(400, ["message" => "Email and password are required"]);
        }

        $email = $data['email'];
        $password = $data['password'];

        $stmt = $this->db_connection->prepare("SELECT id, email, password, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];

                $this->_sendJsonResponse(200, [
                    "message" => "Login successful",
                    "user" => [
                        "id" => $user['id'],
                        "email" => $user['email'],
                        "username" => $user['username']
                    ]
                ]);
            } else {
                $this->_sendJsonResponse(401, ["message" => "Invalid credentials"]);
            }
        } else {
            $this->_sendJsonResponse(404, ["message" => "User not found"]);
        }
        $stmt->close();
    }

    private function register($data) {
        if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
            $this->_sendJsonResponse(400, ["message" => "Username, email, and password are required"]);
        }

        $username = trim($data['username']);
        $email = $data['email'];
        $password = $data['password'];

        if (empty($username)) {
            $this->_sendJsonResponse(400, ["message" => "Username cannot be empty"]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->_sendJsonResponse(400, ["message" => "Invalid email format"]);
        }

        if (strlen($password) < 8) {
            $this->_sendJsonResponse(400, ["message" => "Password must be at least 8 characters long"]);
        }

        $stmt = $this->db_connection->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->fetch_assoc()) {
            $this->_sendJsonResponse(409, ["message" => "User with this email or username already exists"]);
        }
        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db_connection->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $hashed_password);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt->close();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id;

            $this->_sendJsonResponse(201, [
                "message" => "User created successfully",
                "user" => [
                    "id" => $user_id,
                    "username" => $username,
                    "email" => $email
                ]
            ]);
        } else {
            $this->_sendJsonResponse(500, ["message" => "An unexpected error occurred."]);
        }
    }

    public function check_session() {
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->db_connection->prepare("SELECT id, email, username FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                $this->_sendJsonResponse(200, [
                    "loggedIn" => true,
                    "user" => [
                        "id" => $user['id'],
                        "email" => $user['email'],
                        "username" => $user['username']
                    ]
                ]);
            } else {
                $this->_sendJsonResponse(404, ["loggedIn" => false, "message" => "User not found"]);
            }
            $stmt->close();
        } else {
            $this->_sendJsonResponse(401, ["loggedIn" => false, "message" => "Not logged in"]);
        }
    }

    private function logout() {
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();

        $this->_sendJsonResponse(200, ["message" => "Logout successful"]);
    }
}
