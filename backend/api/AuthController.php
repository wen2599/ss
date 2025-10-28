<?php
class AuthController {
    private $db_connection;

    public function __construct() {
        global $db_connection;
        $this->db_connection = $db_connection;
    }

    public function handleRequest() {
        $data = json_decode(file_get_contents("php://input"), true);
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
            default:
                http_response_code(400);
                echo json_encode(["message" => "Invalid action"]);
                break;
        }
    }

    private function login($data) {
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Email and password are required"]);
            return;
        }

        $email = $data['email'];
        $password = $data['password'];

        $stmt = $this->db_connection->prepare("SELECT id, email, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];

                http_response_code(200);
                echo json_encode([
                    "message" => "Login successful",
                    "user" => [
                        "id" => $user['id'],
                        "email" => $user['email']
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(["message" => "Invalid credentials"]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User not found"]);
        }
        $stmt->close();
    }

    private function register($data) {
        if (!isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(["message" => "Email and password are required"]);
            return;
        }

        $email = $data['email'];
        $password = $data['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid email format"]);
            return;
        }

        // Improved password length validation to a minimum of 8 characters
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(["message" => "Password must be at least 8 characters long"]);
            return;
        }

        $stmt = $this->db_connection->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->fetch_assoc()) {
            http_response_code(409);
            echo json_encode(["message" => "User with this email already exists"]);
            $stmt->close();
            return;
        }
        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db_connection->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $hashed_password);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt->close();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id;

            http_response_code(201);
            echo json_encode([
                "message" => "User created successfully",
                "user" => [
                    "id" => $user_id,
                    "email" => $email
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "An unexpected error occurred."]);
        }
    }

    public function check_session() {
        if (isset($_SESSION['user_id'])) {
            $stmt = $this->db_connection->prepare("SELECT id, email FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                http_response_code(200);
                echo json_encode([
                    "loggedIn" => true,
                    "user" => [
                        "id" => $user['id'],
                        "email" => $user['email']
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode(["loggedIn" => false, "message" => "User not found"]);
            }
            $stmt->close();
        } else {
            http_response_code(401);
            echo json_encode(["loggedIn" => false, "message" => "Not logged in"]);
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

        http_response_code(200);
        echo json_encode(["message" => "Logout successful"]);
    }
}
