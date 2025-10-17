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
    // --- Pre-emptive Check for PDO Extension ---
    if (!class_exists('PDO')) {
        $error_msg = "Critical Error: The PDO extension is not installed or enabled in this PHP environment.";
        error_log($error_msg);
        // This is a catastrophic failure; return an error structure that consuming code expects.
        return ['db_error' => $error_msg];
    }
    // --- End Pre-emptive Check ---

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
 *
 * @return array An array of user objects (or an empty array if none found).
 */
function getAllUsers() {
    $pdo = get_db_connection();
    if (is_array($pdo) && isset($pdo['db_error'])) {
        error_log("getAllUsers: Failed to get database connection - " . $pdo['db_error']);
        return [];
    }
    if (!$pdo) return [];

    try {
        $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error in getAllUsers: " . $e->getMessage());
        return [];
    }
}

/**
 * Deletes a user from the database by their email address.
 *
 * @param string $email The email of the user to delete.
 * @return bool True on successful deletion, false otherwise.
 */
function deleteUserByEmail($email) {
    $pdo = get_db_connection();
    if (is_array($pdo) && isset($pdo['db_error'])) {
        error_log("deleteUserByEmail: Failed to get database connection - " . $pdo['db_error']);
        return false;
    }
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
        $stmt->execute([$email]);
        // rowCount() returns the number of affected rows.
        // If it's greater than 0, the deletion was successful.
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error in deleteUserByEmail for {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * Stores lottery results into the lottery_results table.
 *
 * @param string $lotteryType The type of lottery (e.g., "六合彩").
 * @param string $issueNumber The issue number of the lottery drawing.
 * @param string $winningNumbers A comma-separated string of winning numbers.
 * @param string $zodiacSigns A comma-separated string of zodiac signs.
 * @param string $colors A comma-separated string of colors.
 * @param string $drawingDate The date of the drawing in YYYY-MM-DD format.
 * @return bool True on success, false on failure.
 */
function storeLotteryResult($lotteryType, $issueNumber, $winningNumbers, $zodiacSigns, $colors, $drawingDate) {
    $pdo = get_db_connection();
    if (is_array($pdo) && isset($pdo['db_error'])) {
        error_log("storeLotteryResult: Failed to get database connection - " . $pdo['db_error']);
        return false;
    }
    if (!$pdo) {
        error_log("storeLotteryResult: No database connection (returned null unexpectedly).");
        return false;
    }

    try {
        // Check if a result for this lottery type and issue number already exists
        $stmt = $pdo->prepare("SELECT id FROM lottery_results WHERE lottery_type = ? AND issue_number = ?");
        $stmt->execute([$lotteryType, $issueNumber]);
        if ($stmt->fetch()) {
            error_log("Lottery result for type '{$lotteryType}' and issue '{$issueNumber}' already exists. Skipping insertion.");
            return true; // Consider it a success if it already exists
        }

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
