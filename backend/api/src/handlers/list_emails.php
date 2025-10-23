<?php
// Included from /api/index.php

// This script lists emails from the database.

// --- Session Check for Authentication ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAuthenticated = isset($_SESSION['user_id']);

// --- Database Interaction ---
try {
    $pdo = getDbConnection();

    // Base query
    $sql = "SELECT id, sender, subject, received_at, is_private FROM emails";

    // If the user is not authenticated, only show public emails
    if (!$isAuthenticated) {
        $sql .= " WHERE is_private = 0";
    }

    // Add ordering
    $sql .= " ORDER BY received_at DESC";

    // Pagination (optional, but good practice)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = ($page - 1) * $limit;
    
    $sql .= " LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Success Response ---
    jsonResponse(200, [
        'status' => 'success',
        'data' => $emails
    ]);

} catch (PDOException $e) {
    error_log("List-Emails DB Error: " . $e->getMessage());
    jsonError(500, 'Database error while listing emails.');
} catch (Throwable $e) {
    error_log("List-Emails Error: " . $e->getMessage());
    jsonError(500, 'An unexpected error occurred.');
}
