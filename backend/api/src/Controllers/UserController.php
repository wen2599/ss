<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;

class UserController
{
    public function isUserRegistered(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $workerSecret = $params['worker_secret'] ?? null;
        $email = $params['email'] ?? null;

        if ($workerSecret !== $_ENV['EMAIL_HANDLER_SECRET']) {
            $payload = json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $user = User::where('email', $email)->first();
        $isRegistered = $user !== null;

        $payload = json_encode(['status' => 'success', 'data' => ['is_registered' => $isRegistered]]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
