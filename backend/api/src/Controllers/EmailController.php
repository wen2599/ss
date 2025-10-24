<?php
declare(strict_types=1);

namespace App\Controllers;

class EmailController extends BaseController
{
    public function receive(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // --- Security and Data Validation ---
        $workerSecret = $input['worker_secret'] ?? null;
        $expectedSecret = getenv('EMAIL_HANDLER_SECRET');

        if (!$workerSecret || !$expectedSecret || !hash_equals($expectedSecret, $workerSecret)) {
            $this->jsonError(403, 'Forbidden: Invalid worker secret.');
            return;
        }

        $requiredFields = ['from', 'subject', 'body', 'user_id'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field])) {
                $this->jsonError(400, "Missing required field: {$field}.");
                return;
            }
        }

        $sender = trim($input['from']);
        $subject = trim($input['subject']);
        $body = trim($input['body']);
        $userId = $input['user_id'];
        $isPrivate = filter_var($input['is_private'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($sender) || empty($subject) || empty($body) || !is_numeric($userId)) {
            $this->jsonError(400, 'Sender, subject, body cannot be empty, and user_id must be a number.');
            return;
        }

        try {
            $pdo = getDbConnection();
            
            // --- Verify User Existence ---
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            if (!$userStmt->fetch()) {
                $this->jsonError(404, "User with ID {$userId} not found.");
                return;
            }

            // --- Insert Email ---
            $stmt = $pdo->prepare("INSERT INTO emails (sender, subject, body, user_id, is_private) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sender, $subject, $body, $userId, $isPrivate ? 1 : 0]);
            
            $emailId = $pdo->lastInsertId();
            $this->jsonResponse(201, ['status' => 'success', 'message' => 'Email received and linked to user successfully.', 'data' => ['email_id' => $emailId]]);
        
        } catch (\PDOException $e) {
            error_log("Receive-Email DB Error: " . $e->getMessage());
            $this->jsonError(500, 'Database error while storing the email.');
        }
    }

    public function list(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $isAuthenticated = isset($_SESSION['user_id']);

        try {
            $pdo = getDbConnection();
            $sql = "SELECT id, sender, subject, received_at, is_private FROM emails";
            if (!$isAuthenticated) {
                $sql .= " WHERE is_private = 0";
            }
            $sql .= " ORDER BY received_at DESC";

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;
            $sql .= " LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $emails = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonResponse(200, ['status' => 'success', 'data' => $emails]);
        } catch (\PDOException $e) {
            error_log("List-Emails DB Error: " . $e->getMessage());
            $this->jsonError(500, 'Database error while listing emails.');
        }
    }

    public function get(int $id): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $isAuthenticated = isset($_SESSION['user_id']);

        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = ?");
            $stmt->execute([$id]);
            $email = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$email) {
                $this->jsonError(404, 'Email not found.');
            }

            if ($email['is_private'] && !$isAuthenticated) {
                $this->jsonError(403, 'Forbidden: You do not have permission to view this email.');
            }

            $this->jsonResponse(200, ['status' => 'success', 'data' => $email]);
        } catch (\PDOException $e) {
            error_log("Get-Email DB Error: " . $e->getMessage());
            $this->jsonError(500, 'Database error while retrieving the email.');
        }
    }
}
