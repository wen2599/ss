<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Email;
use Illuminate\Database\Capsule\Manager as DB;

class EmailController
{
    public function receiveEmail(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        // Security check for the worker secret
        if (($body['worker_secret'] ?? null) !== $_ENV['EMAIL_HANDLER_SECRET']) {
            $payload = json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        // Input validation
        $requiredFields = ['from', 'to', 'subject', 'body'];
        foreach ($requiredFields as $field) {
            if (empty($body[$field])) {
                $payload = json_encode(['status' => 'error', 'message' => "Missing required field: {$field}"]);
                $response->getBody()->write($payload);
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }

        $email = new Email();
        $email->from_address = $body['from'];
        $email->to_address = $body['to'];
        $email->subject = $body['subject'];
        $email->raw_content = $body['body'];
        $email->save();

        $payload = json_encode(['status' => 'success', 'data' => $email]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function listEmails(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 15;

        $emails = Email::paginate($perPage, ['*'], 'page', $page);

        $payload = json_encode(['status' => 'success', 'data' => $emails]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getEmail(Request $request, Response $response, array $args): Response
    {
        $email = Email::find($args['id']);
        if (!$email) {
            $payload = json_encode(['status' => 'error', 'message' => 'Email not found']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        $payload = json_encode(['status' => 'success', 'data' => $email]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
