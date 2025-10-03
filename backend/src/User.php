<?php

namespace App;

use PDO;
use PDOException;

/**
 * Class User
 *
 * A library class for handling user-related database operations.
 */
class User {

    /**
     * Finds a user in the database by their unique ID.
     *
     * @param PDO $pdo The database connection object.
     * @param int $userId The ID of the user to find.
     * @return array|null An associative array containing the user's data if found, otherwise null.
     * @throws PDOException If a database error occurs during the query.
     */
    public static function findById(PDO $pdo, int $userId): ?array
    {
        global $log; // Use the global logger from init.php

        try {
            $sql = "SELECT id, email, username, status, winning_rate FROM users WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $userId]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $log->info("User found by ID.", ['user_id' => $userId]);
                return $user;
            } else {
                $log->warning("User not found for ID.", ['user_id' => $userId]);
                return null;
            }

        } catch (PDOException $e) {
            $log->error("Database error while finding user by ID.", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            // Re-throw the exception to be handled by the global error handler.
            throw $e;
        }
    }
}
?>