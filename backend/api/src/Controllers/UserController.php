<?php
declare(strict_types=1);

namespace App\Controllers;

class UserController extends BaseController
{
    public function register(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['email']) || !isset($input['password'])) {
            $this->jsonError(400, 'Email and password are required.');
        }

        $email = trim($input['email']);
        $password = $input['password'];

        if (empty($email) || empty($password)) {
            $this->jsonError(400, 'Email and password cannot be empty.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonError(400, 'Invalid email format.');
        }

        if (strlen($password) < 6) {
            $this->jsonError(400, 'Password must be at least 6 characters long.');
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $this->jsonError(409, 'Email already exists.');
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$email, $hashedPassword]);

            $this->jsonResponse(201, ['status' => 'success', 'message' => 'User registered successfully.']);
        } catch (\PDOException $e) {
            error_log("Registration DB Error: " . $e->getMessage());
            $this->jsonError(500, 'Database error during registration.');
        }
    }

    public function login(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['lifetime' => 86400, 'path' => '/', 'samesite' => 'Lax']);
            session_start();
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['email']) || !isset($input['password'])) {
            $this->jsonError(400, 'Email and password are required.');
        }

        $email = trim($input['email']);
        $password = $input['password'];

        if (empty($email) || empty($password)) {
            $this->jsonError(400, 'Email and password cannot be empty.');
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->jsonError(401, 'Invalid email or password.');
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            $this->jsonResponse(200, ['status' => 'success', 'message' => 'Login successful.', 'data' => ['username' => $user['username']]]);
        } catch (\PDOException $e) {
            error_log("Login DB Error: " . $e->getMessage());
            $this->jsonError(500, 'Database error during login.');
        }
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        $this->jsonResponse(200, ['status' => 'success', 'message' => 'Logout successful.']);
    }

    public function isRegistered(): void
    {
        $workerSecret = $_GET['worker_secret'] ?? null;
        $expectedSecret = getenv('EMAIL_HANDLER_SECRET');

        // Security Check: Validate the secret from the worker
        if (!$workerSecret || !$expectedSecret || !hash_equals($expectedSecret, $workerSecret)) {
            $this->jsonError(403, 'Forbidden: Invalid worker secret.');
            return;
        }

        if (!isset($_GET['email'])) {
            $this->jsonError(400, 'Email parameter is required.');
            return;
        }

        $email = trim($_GET['email']);
        if (empty($email)) {
            $this->jsonError(400, 'Email cannot be empty.');
            return;
        }

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // User is found, return success with their ID
                $this->jsonResponse(200, [
                    'status' => 'success',
                    'data' => [
                        'is_registered' => true,
                        'user_id' => $user['id']
                    ]
                ]);
            } else {
                // User is not found
                $this->jsonResponse(200, [
                    'status' => 'success',
                    'data' => ['is_registered' => false]
                ]);
            }
        } catch (\PDOException $e) {
            error_log("Is-Registered DB Error: " . $e->getMessage());
            $this->jsonError(500, 'Database error while checking email.');
        }
    }
}
