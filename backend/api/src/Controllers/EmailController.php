<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Email;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EmailController
{
    /**
     * Receive a new email from the Cloudflare Worker.
     */
    public function receiveEmail(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $workerSecret = $request->getHeaderLine('X-Worker-Secret');

        // --- Security Check ---
        $expectedSecret = $_ENV['WORKER_SECRET'];
        if (!$expectedSecret || $workerSecret !== $expectedSecret) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // --- Validation ---
        if (empty($data['from']) || empty($data['to']) || empty($data['raw'])) {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Missing required fields: from, to, raw']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // --- Store Email ---
        try {
            $email = Email::create([
                'from_address' => $data['from'],
                'to_address' => $data['to'],
                'subject' => $data['subject'] ?? null,
                'raw_content' => $data['raw'],
                'html_content' => $data['html'] ?? null,
                'parsed_data' => $data['parsed_data'] ?? null,
                'worker_secret_provided' => true,
            ]);

            $response->getBody()->write(json_encode(['status' => 'success', 'message' => 'Email received', 'id' => $email->id]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            // Basic error logging
            error_log("Failed to save email: " . $e->getMessage());
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Could not save email to the database.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * List all emails with pagination.
     */
    public function listEmails(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? (int) $queryParams['page'] : 1;
        $perPage = isset($queryParams['limit']) ? (int) $queryParams['limit'] : 15;

        try {
            $emails = Email::select('id', 'from_address', 'subject', 'received_at')
                ->orderBy('received_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $response->getBody()->write($emails->toJson());
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Failed to list emails: " . $e->getMessage());
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Could not retrieve emails.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a single email by its ID.
     */
    public function getEmail(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        try {
            $email = Email::find($id);

            if (!$email) {
                $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Email not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write($email->toJson());
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Failed to get email {$id}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'Could not retrieve email.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
