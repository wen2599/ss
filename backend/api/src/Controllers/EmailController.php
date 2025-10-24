<?php
declare(strict_types=1);

namespace App\Controllers;

class EmailController extends BaseController
{
    public function saveEmail(): void
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
