<?php
declare(strict_types=1);

namespace App\Controllers;

class EmailController extends BaseController
{
    public function handleEmails(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $pathParts = explode('/', trim($path, '/'));

        // Check for an ID in the URL, e.g., /api/emails/123
        $emailId = null;
        if (isset($pathParts[2]) && is_numeric($pathParts[2])) {
            $emailId = (int)$pathParts[2];
        }

        switch ($method) {
            case 'GET':
                if ($emailId) {
                    $this->getEmail($emailId);
                } else {
                    $this->listEmails();
                }
                break;
            case 'POST':
                $this->saveEmail();
                break;
            default:
                $this->jsonResponse(405, ['status' => 'error', 'message' => 'Method Not Allowed']);
                break;
        }
    }

    private function listEmails(): void
    {
        // Session check is now required for this endpoint
        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse(401, ['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        try {
            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("SELECT id, sender, subject, received_at FROM emails WHERE user_id = ? ORDER BY received_at DESC");
            $stmt->execute([$_SESSION['user_id']]);
            $emails = $stmt->fetchAll();
            $this->jsonResponse(200, ['status' => 'success', 'data' => $emails]);
        } catch (\PDOException $e) {
            error_log('Database error in listEmails: ' . $e->getMessage());
            $this->jsonResponse(500, ['status' => 'error', 'message' => 'Database error']);
        }
    }

    private function getEmail(int $id): void
    {
        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse(401, ['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        try {
            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $email = $stmt->fetch();

            if ($email) {
                $this->jsonResponse(200, ['status' => 'success', 'data' => $email]);
            } else {
                $this->jsonResponse(404, ['status' => 'error', 'message' => 'Email not found or access denied']);
            }
        } catch (\PDOException $e) {
            error_log('Database error in getEmail: ' . $e->getMessage());
            $this->jsonResponse(500, ['status' => 'error', 'message' => 'Database error']);
        }
    }

    private function saveEmail(): void
    {
        // 1. Security Check: Verify worker secret
        $data = $this->getJsonBody();
        $workerSecret = $data['worker_secret'] ?? null;
        $expectedSecret = $_ENV['EMAIL_HANDLER_SECRET'] ?? null;

        if (!$workerSecret || !$expectedSecret || $workerSecret !== $expectedSecret) {
            $this->jsonResponse(403, ['status' => 'error', 'message' => 'Forbidden: Invalid or missing secret.']);
            return;
        }

        // 2. Input Validation
        $from = $data['from'] ?? null;
        $subject = $data['subject'] ?? null;
        $body = $data['body'] ?? null;
        $userId = $data['user_id'] ?? null;

        if (!$from || !$subject || !$body || !$userId) {
            $this->jsonResponse(400, ['status' => 'error', 'message' => 'Missing required email data.']);
            return;
        }

        try {
            // 3. Database Insertion
            $pdo = $this->getDbConnection();
            $stmt = $pdo->prepare(
                "INSERT INTO emails (user_id, sender, subject, body, received_at) VALUES (?, ?, ?, ?, NOW())"
            );

            if ($stmt->execute([$userId, $from, $subject, $body])) {
                $this->jsonResponse(201, ['status' => 'success', 'message' => 'Email saved successfully.']);
            } else {
                $this->jsonResponse(500, ['status' => 'error', 'message' => 'Failed to save email.']);
            }
        } catch (\PDOException $e) {
            error_log('Database error in saveEmail: ' . $e->getMessage());
            $this->jsonResponse(500, ['status' => 'error', 'message' => 'Database error.']);
        }
    }
}
