<?php
/**
 * Establishes and returns a singleton PDO database connection.
 *
 * This function reads database credentials from environment variables and uses a static
 * variable to ensure that only one database connection is made per request lifecycle.
 * This is crucial for performance and resource management.
 *
 * @return PDO|array|null A configured PDO object on success, an array with 'db_error' key on connection failure, or null (deprecated).
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        // Load credentials from environment variables.
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $dbname = getenv('DB_DATABASE');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');

        // --- DEBUGGING: Log DB credentials before connection attempt ---
        error_log("DEBUG DB: Host: {$host}, Port: {$port}, DBName: {$dbname}, User: {$user}");
        // --- END DEBUGGING ---

        // All credentials are required.
        if (empty($host) || empty($port) || empty($dbname) || empty($user)) {
             $error_msg = "Database connection error: Required environment variables are not set. Check DB_HOST, DB_PORT, DB_DATABASE, DB_USER in .env";
             error_log($error_msg);
             return ['db_error' => $error_msg]; // Return specific error
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Fetch as associative arrays.
            PDO::ATTR_EMULATE_PREPARES   => false,              // Use native prepared statements.
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            error_log("DEBUG DB: Database connection attempt successful.");
        } catch (PDOException $e) {
            // Log error securely, don't expose to the user.
            $error_msg = "Database connection failed: " . $e->getMessage() . " | DSN: {$dsn} | User: {$user}";
            error_log($error_msg);
            return ['db_error' => $error_msg]; // Return specific error
        }
    }

    return $pdo;
}

/**
 * Retrieves all users from the database.
 * @param PDO $pdo The active database connection.
 * @return array An array of user objects (or an empty array if none found).
 */
function getAllUsers(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getAllUsers: " . $e->getMessage());
        return []; // Return empty array on failure
    }
}

/**
 * Deletes a user from the database by their email address.
 * @param PDO $pdo The active database connection.
 * @param string $email The email of the user to delete.
 * @return bool True on successful deletion, false otherwise.
 */
function deleteUserByEmail(PDO $pdo, $email) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error in deleteUserByEmail for {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * Stores lottery results into the lottery_results table.
 * @param PDO $pdo The active database connection.
 * @param string $lotteryType The type of lottery (e.g., "六合彩").
 * @param string $issueNumber The issue number of the lottery drawing.
 * @param string $winningNumbers A comma-separated string of winning numbers.
 * @param string $zodiacSigns A comma-separated string of zodiac signs.
 * @param string $colors A comma-separated string of colors.
 * @param string $drawingDate The date of the drawing in YYYY-MM-DD format.
 * @return bool True on success, false on failure.
 */
function storeLotteryResult(PDO $pdo, $lotteryType, $issueNumber, $winningNumbers, $zodiacSigns, $colors, $drawingDate) {
    try {
        // Check for existing result first
        $stmt = $pdo->prepare("SELECT id FROM lottery_results WHERE lottery_type = ? AND issue_number = ?");
        $stmt->execute([$lotteryType, $issueNumber]);
        if ($stmt->fetch()) {
            error_log("Lottery result for type '{$lotteryType}' and issue '{$issueNumber}' already exists. Skipping insertion.");
            return true; // Already exists, count as success.
        }

        // Insert new result
        $stmt = $pdo->prepare(
            "INSERT INTO lottery_results (
                lottery_type, 
                issue_number, 
                winning_numbers, 
                zodiac_signs, 
                colors, 
                drawing_date
            ) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $lotteryType,
            $issueNumber,
            $winningNumbers,
            $zodiacSigns,
            $colors,
            $drawingDate
        ]);
        error_log("Successfully stored lottery result for {$lotteryType} - {$issueNumber}.");
        return true;
    } catch (PDOException $e) {
        error_log("Error storing lottery result for {$lotteryType} - {$issueNumber}: " . $e->getMessage());
        return false;
    }
}
