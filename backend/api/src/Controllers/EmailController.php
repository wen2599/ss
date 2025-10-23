<?php
namespace App\Controllers;

use PDO;
use PDOException;
use Throwable;

class EmailController
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Handles receiving a new email.
     */
    public function receive()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['sender']) || !isset($input['subject']) || !isset($input['body'])) {
            jsonError(400, 'Missing required email fields: sender, subject, and body.');
        }

        $sender = trim($input['sender']);
        $subject = trim($input['subject']);
        $body = trim($input['body']);
        $isPrivate = filter_var($input['is_private'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (empty($sender) || empty($subject) || empty($body)) {
            jsonError(400, 'Sender, subject, and body cannot be empty.');
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO emails (sender, subject, body, is_private) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$sender, $subject, $body, $isPrivate ? 1 : 0]);
            $emailId = $this->pdo->lastInsertId();

            jsonResponse(201, [
                'status' => 'success',
                'message' => 'Email received and stored successfully.',
                'data' => ['email_id' => $emailId]
            ]);
        } catch (PDOException $e) {
            error_log("Receive-Email DB Error: " . $e->getMessage());
            jsonError(500, 'Database error while storing the email.');
        }
    }

    /**
     * Lists all emails, filtering for privacy.
     */
    public function list()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $isAuthenticated = isset($_SESSION['user_id']);

        try {
            $sql = "SELECT id, sender, subject, received_at, is_private FROM emails";
            if (!$isAuthenticated) {
                $sql .= " WHERE is_private = 0";
            }
            $sql .= " ORDER BY received_at DESC";

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = ($page - 1) * $limit;

            $sql .= " LIMIT :limit OFFSET :offset";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(200, ['status' => 'success', 'data' => $emails]);
        } catch (PDOException $e) {
            error_log("List-Emails DB Error: " . $e->getMessage());
            jsonError(500, 'Database error while listing emails.');
        }
    }

    /**
     * Gets a single email by its ID.
     */
    public function get($id)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $isAuthenticated = isset($_SESSION['user_id']);
        $emailId = (int)$id;

        if ($emailId <= 0) {
            jsonError(400, 'Invalid Email ID.');
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM emails WHERE id = ?");
            $stmt->execute([$emailId]);
            $email = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$email) {
                jsonError(404, 'Email not found.');
            }

            if ($email['is_private'] && !$isAuthenticated) {
                jsonError(403, 'Forbidden: You do not have permission to view this email.');
            }

            jsonResponse(200, ['status' => 'success', 'data' => $email]);
        } catch (PDOException $e) {
            error_log("Get-Email DB Error: " . $e->getMessage());
            jsonError(500, 'Database error while retrieving the email.');
        }
    }
}
