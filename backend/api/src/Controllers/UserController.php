<?php
declare(strict_types=1);

namespace App\Controllers;

use InvalidArgumentException;
use PDOException;

class UserController extends BaseController
{
    /**
     * Handles user registration.
     * Expects 'email', 'password', 'telegram_chat_id' (optional), 'telegram_username' (optional) in JSON body.
     */
    public function register(): void
    {
        try {
            $data = $this->getJsonBody();
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;
            $telegramChatId = $data['telegram_chat_id'] ?? null;
            $telegramUsername = $data['telegram_username'] ?? null;

            // Input Validation
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid or missing email.');
            }

            if (empty($password) || !is_string($password) || strlen($password) < 6) {
                throw new InvalidArgumentException('Password must be at least 6 characters long.');
            }

            if (!is_null($telegramChatId) && (!is_string($telegramChatId) || empty($telegramChatId))) {
                throw new InvalidArgumentException('Invalid telegram_chat_id.');
            }

            if (!is_null($telegramUsername) && (!is_string($telegramUsername) || empty($telegramUsername))) {
                throw new InvalidArgumentException('Invalid telegram_username.');
            }

            $pdo = $this->getDbConnection();

            // Check if email already registered
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $this->jsonError(409, 'Email already registered.');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password_hash, telegram_chat_id, telegram_username) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$email, $passwordHash, $telegramChatId, $telegramUsername])) {
                $this->jsonResponse(201, ['status' => 'success', 'message' => 'User registered successfully.']);
            } else {
                // This should ideally not be reached if PDO::ATTR_ERRMODE is EXCEPTION
                $this->jsonError(500, 'Failed to register user.');
            }
        } catch (InvalidArgumentException $e) {
            $this->jsonError(400, $e->getMessage(), $e);
        } catch (PDOException $e) {
            error_log('Database error during registration: ' . $e->getMessage());
            $this->jsonError(500, 'Database error during registration.', $e);
        } catch (Throwable $e) {
            $this->jsonError(500, 'An unexpected error occurred during registration.', $e);
        }
    }

    /**
     * Handles user login.
     * Expects 'email' and 'password' in JSON body.
     */
    public function login(): void
    {
        try {
            $data = $this->getJsonBody();
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            // Input Validation
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid or missing email for login.');
            }
            if (empty($password)) {
                throw new InvalidArgumentException('Password is required for login.');
            }

            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $email;
                $this->jsonResponse(200, ['status' => 'success', 'message' => 'Login successful.', 'data' => ['username' => $email]]);
            } else {
                $this->jsonError(401, 'Invalid credentials.');
            }
        } catch (InvalidArgumentException $e) {
            $this->jsonError(400, $e->getMessage(), $e);
        } catch (PDOException $e) {
            error_log('Database error during login: ' . $e->getMessage());
            $this->jsonError(500, 'Database error during login.', $e);
        } catch (Throwable $e) {
            $this->jsonError(500, 'An unexpected error occurred during login.', $e);
        }
    }

    /**
     * Handles user logout.
     * Clears session data.
     */
    public function logout(): void
    {
        session_unset();
        session_destroy();
        $this->jsonResponse(200, ['status' => 'success', 'message' => 'Logged out successfully.']);
    }

    /**
     * Checks if a user is currently authenticated.
     */
    public function checkAuth(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->jsonResponse(200, ['status' => 'success', 'data' => ['isLoggedIn' => true, 'username' => $_SESSION['username']]]);
        } else {
            $this->jsonResponse(200, ['status' => 'success', 'data' => ['isLoggedIn' => false]]);
        }
    }

    /**
     * Checks if a user is registered, intended for Telegram worker integration.
     * Requires a valid 'worker_secret' in query parameters for authorization.
     * Expects 'email' in query parameters.
     */
    public function isRegistered(): void
    {
        try {
            // Security Check: Verify worker secret using hash_equals for timing attack prevention
            $workerSecret = $_GET['worker_secret'] ?? '';
            $expectedSecret = $_ENV['WORKER_SECRET'] ?? '';

            if (empty($workerSecret) || empty($expectedSecret) || !hash_equals($expectedSecret, $workerSecret)) {
                $this->jsonError(403, 'Forbidden: Invalid or missing secret.');
            }

            $email = $_GET['email'] ?? null;
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid or missing email parameter.');
            }

            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("SELECT id, telegram_chat_id FROM users WHERE username = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user) {
                $this->jsonResponse(200, [
                    'status' => 'success',
                    'data' => [
                        'is_registered' => true,
                        'user_id' => $user['id'],
                        'telegram_chat_id' => $user['telegram_chat_id'],
                    ]
                ]);
            } else {
                $this->jsonResponse(200, [
                    'status' => 'success',
                    'data' => ['is_registered' => false]
                ]);
            }
        } catch (InvalidArgumentException $e) {
            $this->jsonError(400, $e->getMessage(), $e);
        } catch (PDOException $e) {
            error_log('Database error in isRegistered: ' . $e->getMessage());
            $this->jsonError(500, 'Database error in isRegistered.', $e);
        } catch (Throwable $e) {
            $this->jsonError(500, 'An unexpected error occurred during isRegistered check.', $e);
        }
    }
}
