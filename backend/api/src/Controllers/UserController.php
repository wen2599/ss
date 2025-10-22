<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;

class UserController
{
    /**
     * Handles user registration.
     */
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // 1. Basic Validation
        if (!isset($data['email']) || !isset($data['password']) || empty($data['email']) || empty($data['password'])) {
            return $this->jsonError($response, '邮箱和密码不能为空。', 400);
        }

        $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return $this->jsonError($response, '无效的邮箱格式。', 400);
        }

        $password = $data['password'];
        if (strlen($password) < 6) {
            return $this->jsonError($response, '密码长度不能少于6位。', 400);
        }

        // 2. Check for existing user
        if (User::where('email', $email)->exists()) {
            return $this->jsonError($response, '该邮箱已被注册。', 409);
        }

        // 3. Create and save the new user
        try {
            $user = new User();
            $user->username = $email;
            $user->email = $email;
            $user->password = password_hash($password, PASSWORD_BCRYPT);
            $user->save();
        } catch (\Exception $e) {
            // Log the actual error for debugging
            error_log('User creation failed: ' . $e->getMessage());
            return $this->jsonError($response, '注册失败，请稍后再试。', 500);
        }

        // 4. Start session and log in the new user
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;

        // 5. Return success response
        $payload = json_encode([
            'status' => 'success',
            'data' => [
                'message' => '注册成功！',
                'user_id' => $user->id
            ]
        ]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * Handles user login.
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (!isset($data['email']) || !isset($data['password']) || empty($data['email']) || empty($data['password'])) {
            return $this->jsonError($response, '邮箱和密码不能为空。', 400);
        }

        $email = $data['email'];
        $password = $data['password'];

        $user = User::where('email', $email)->first();

        if ($user && password_verify($password, $user->password)) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;

            $payload = json_encode(['status' => 'success', 'data' => ['message' => '登录成功。']]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $this->jsonError($response, '邮箱或密码错误。', 401);
    }

    /**
     * Handles user logout.
     */
    public function logout(Request $request, Response $response): Response
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $payload = json_encode(['status' => 'success', 'data' => ['message' => '已成功登出。']]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Checks if a user is registered (used by the worker).
     */
    public function isUserRegistered(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $workerSecret = $params['worker_secret'] ?? null;
        $email = $params['email'] ?? null;

        if (!$workerSecret || $workerSecret !== ($_ENV['EMAIL_HANDLER_SECRET'] ?? '')) {
            return $this->jsonError($response, 'Unauthorized', 401);
        }

        if (empty($email)) {
             return $this->jsonError($response, 'Email is required.', 400);
        }

        $user = User::where('email', $email)->first();
        $isRegistered = $user !== null;
        $userId = $isRegistered ? $user->id : null;

        $payload = json_encode([
            'status' => 'success',
            'data' => [
                'is_registered' => $isRegistered,
                'user_id' => $userId
            ]
        ]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Helper to create a standardized JSON error response.
     */
    private function jsonError(Response $response, string $message, int $statusCode): Response
    {
        $payload = json_encode(['status' => 'error', 'message' => $message]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
