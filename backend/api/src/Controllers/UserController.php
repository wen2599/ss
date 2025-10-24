<?php
declare(strict_types=1);

namespace App\Controllers;

class UserController extends BaseController
{
    public function register(): void
    {
        error_log('Register method called');
        $data = $this->getJsonBody();
        error_log('Request body: ' . json_encode($data));
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Invalid or missing email');
            $this->jsonResponse(400, ['status' => 'error', 'message' => 'Invalid or missing email.']);
            return;
        }

        if (!$password || strlen($password) < 6) {
            error_log('Password too short');
            $this->jsonResponse(400, ['status' => 'error', 'message' => 'Password must be at least 6 characters long.']);
            return;
        }

        try {
            error_log('Connecting to database');
            $pdo = $this->getDbConnection();
            error_log('Database connection successful');

            // Check if user already exists
            error_log('Checking if user exists');
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                error_log('User already exists');
                $this->jsonResponse(409, ['status' => 'error', 'message' => 'Email already registered.']);
                return;
            }
            error_log('User does not exist');

            // Hash password and insert user
            error_log('Hashing password');
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            error_log('Password hashed');
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            error_log('Executing insert');
            if ($stmt->execute([$email, $passwordHash])) {
                error_log('User registered successfully');
                $this->jsonResponse(201, ['status' => 'success', 'message' => 'User registered successfully.']);
            } else {
                error_log('Failed to register user');
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
                // Start session
                session_start();
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
        session_start();
        session_unset();
        session_destroy();
        $this->jsonResponse(200, ['status' => 'success', 'message' => 'Logged out successfully.']);
    }

    public function checkAuth(): void
    {
        session_start();
        if (isset($_SESSION['user_id'])) {
            $this->jsonResponse(200, ['status' => 'success', 'data' => ['isLoggedIn' => true, 'username' => $_SESSION['username']]]);
        } else {
            $this->jsonResponse(200, ['status' => 'success', 'data' => ['isLoggedIn' => false]]);
        }
    }
}
