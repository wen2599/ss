<?php
declare(strict_types=1);

namespace App\Controllers;

use InvalidArgumentException;
use PDOException;
use Throwable;

class UserController extends BaseController
{
    /**
     * Handles user registration.
     * After successful registration, the user is automatically logged in.
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

            // Optional fields validation
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
                return; // Stop execution
            }

            // Hash password and insert user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password_hash, telegram_chat_id, telegram_username) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([$email, $passwordHash, $telegramChatId, $telegramUsername]);
            
            // Automatically log in the user after registration
            $userId = $pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$userId;
            $_SESSION['username'] = $email;

            // Standard practice is to return 200 OK on register/login, not 201.
            // Let the client redirect to a login flow.
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'User registered successfully. Please log in.'
            ], 200);

        } catch (InvalidArgumentException $e) {
            $this->jsonError(400, $e->getMessage(), $e);
        } catch (PDOException $e) {
            // Check for unique constraint violation (error code 23000)
            if ($e->getCode() == 23000) {
                 $this->jsonError(409, 'Email already registered.', $e);
            } else {
                error_log('Database error during registration: ' . $e->getMessage());
                $this->jsonError(500, 'Database error during registration.', $e);
            }
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
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];

                $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Login successful.',
                    'data' => [
                        'user_id' => (int)$user['id'],
                        'username' => $user['username']
                    ]
                ]);
            } else {
                // To prevent user enumeration, use a generic error message for both non-existent user and wrong password.
                $this->jsonError(401, 'Login failed. Please check your email and password.');
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
        // Ensure the session cookie is also cleared
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        $this->jsonResponse(200, ['status' => 'success', 'message' => 'Logged out successfully.']);
    }

    /**
     * Checks if a user is currently authenticated and returns user data.
     * Returns 401 if not authenticated.
     */
    public function checkAuth(): void
    {
        if (isset($_SESSION['user_id']) && is_int($_SESSION['user_id']) && isset($_SESSION['username'])) {
            $this->jsonResponse([
                'status' => 'success',
                'data' => [
                    'isLoggedIn' => true,
                    'user_id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username']
                ]
            ]);
        } else {
            // A check endpoint should return a 200 OK with the auth status, not a 401.
            $this->jsonResponse([
                'status' => 'success',
                'data' => ['isLoggedIn' => false]
            ]);
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
            // Security Check: Verify worker secret
            $workerSecret = $_GET['worker_secret'] ?? '';
            $expectedSecret = $_ENV['WORKER_SECRET'] ?? '';
            if (empty($workerSecret) || empty($expectedSecret) || !hash_equals($expectedSecret, $workerSecret)) {
                $this->jsonError(403, 'Forbidden: Invalid or missing secret.');
                return;
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
