<?php
declare(strict_types=1);

namespace App\Controllers;

class UserController extends BaseController
{
    public function register(): void
    {
        $data = $this->getJsonBody();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(400, ['status' => 'error', 'message' => 'Invalid or missing email.']);
            return;
        }

        if (!$password || strlen($password) < 6) {
            $this->jsonResponse(400, ['status' => 'error', 'message' => 'Password must be at least 6 characters long.']);
            return;
        }

        try {
            $pdo = $this->getDbConnection();

            // Check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $this->jsonResponse(409, ['status' => 'error', 'message' => 'Email already registered.']);
                return;
            }

            // Hash password and insert user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            if ($stmt->execute([$email, $passwordHash])) {
                $this->jsonResponse(201, ['status' => 'success', 'message' => 'User registered successfully.']);
            } else {
                $this->jsonResponse(500, ['status' => 'error', 'message' => 'Failed to register user.']);
            }
        } catch (\PDOException $e) {
            error_log('Database error during registration: ' . $e->getMessage());
            $this->jsonResponse(500, ['status' => 'error', 'message' => 'Database error during registration.']);
        }
    }

    public function login(): void
    {
        $data = $this->getJsonBody();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            $this->jsonResponse(400, ['status' => 'error', 'message' => 'Email and password are required.']);
            return;
        }

        try {
            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Start session and regenerate ID
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $email;
                $this->jsonResponse(200, ['status' => 'success', 'message' => 'Login successful.', 'data' => ['username' => $email]]);
            } else {
                $this->jsonResponse(401, ['status' => 'error', 'message' => 'Invalid credentials.']);
            }
        } catch (\PDOException $e) {
            $this->jsonResponse(500, ['status' => 'error', 'message' => 'Database error during login.']);
        }
    }

    public function logout(): void
    {
        session_unset();
        session_destroy();
        $this->jsonResponse(200, ['status' => 'success', 'message' => 'Logged out successfully.']);
    }

    public function checkAuth(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->jsonResponse(200, ['status' => 'success', 'data' => ['isLoggedIn' => true, 'username' => $_SESSION['username']]]);
        } else {
            $this->jsonResponse(200, ['status' => 'success', 'data' => ['isLoggedIn' => false]]);
        }
    }

    public function isRegistered(): void
    {
        // 1. Security Check: Verify worker secret
        $workerSecret = $_GET['worker_secret'] ?? null;
        $expectedSecret = $_ENV['WORKER_SECRET'] ?? null;

        if (!$workerSecret || !$expectedSecret || $workerSecret !== $expectedSecret) {
            $this->jsonResponse(403, ['status' => 'error', 'message' => 'Forbidden: Invalid or missing secret.']);
            return;
        }

        // 2. Input Validation
        $email = $_GET['email'] ?? null;
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(400, ['status' => 'error', 'message' => 'Invalid or missing email parameter.']);
            return;
        }

        try {
            // 3. Database Lookup
            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            // 4. JSON Response
            if ($user) {
                $this->jsonResponse(200, [
                    'status' => 'success',
                    'data' => [
                        'is_registered' => true,
                        'user_id' => $user['id']
                    ]
                ]);
            } else {
                $this->jsonResponse(200, [
                    'status' => 'success',
                    'data' => ['is_registered' => false]
                ]);
            }
        } catch (\PDOException $e) {
            error_log('Database error in isRegistered: ' . $e->getMessage());
            $this->jsonResponse(500, ['status' => 'error', 'message' => 'Database error.']);
        }
    }
}
