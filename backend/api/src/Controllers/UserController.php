<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;

class UserController
{
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (empty($email) || empty($password)) {
            return $this->jsonError($response, 'Email and password are required.', 400);
        }

        if (User::where('email', $email)->exists()) {
            return $this->jsonError($response, 'A user with this email already exists.', 409);
        }

        $user = new User();
        $user->username = $email; // Use email as username
        $user->email = $email;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->save();

        // Immediately log the user in by setting up the session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;


        $payload = json_encode(['status' => 'success', 'data' => ['message' => 'Registration successful.', 'user_id' => $user->id]]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (empty($email) || empty($password)) {
            return $this->jsonError($response, 'Email and password are required.', 400);
        }

        $user = User::where('email', $email)->first();

        if ($user && password_verify($password, $user->password)) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;

            $payload = json_encode(['status' => 'success', 'data' => ['message' => 'Login successful.']]);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $this->jsonError($response, 'Invalid credentials.', 401);
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        $payload = json_encode(['status' => 'success', 'data' => ['message' => 'Logout successful.']]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function isUserRegistered(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $workerSecret = $params['worker_secret'] ?? null;
        $email = $params['email'] ?? null;

        if ($workerSecret !== $_ENV['EMAIL_HANDLER_SECRET']) {
            return $this->jsonError($response, 'Unauthorized', 401);
        }

        $user = User::where('email', $email)->first();
        $isRegistered = $user !== null;
        $userId = $isRegistered ? $user->id : null;

        $payload = json_encode(['status' => 'success', 'data' => ['is_registered' => $isRegistered, 'user_id' => $userId]]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function jsonError(Response $response, string $message, int $statusCode): Response
    {
        $payload = json_encode(['status' => 'error', 'message' => $message]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
    }
}
