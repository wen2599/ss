<?php
/**
 * db_operations.php (MODIFIED TO BE SELF-SUFFICIENT)
 * This file now includes its own lightweight .env loader at the top.
 * This ensures that any script including this file will have the necessary
 * database environment variables loaded automatically.
 */

// Environment variables are expected to be loaded by the server environment.
// No .env file loading is performed here.


/**
 * Establishes and returns a singleton PDO database connection.
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $dbname = getenv('DB_DATABASE');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASSWORD');

        // This check is now more likely to pass.
        $missing_vars = [];
        if (empty($host)) $missing_vars[] = 'DB_HOST';
        if (empty($port)) $missing_vars[] = 'DB_PORT';
        if (empty($dbname)) $missing_vars[] = 'DB_DATABASE';
        if (empty($user)) $missing_vars[] = 'DB_USER';

        if (!empty($missing_vars)) {
            $error_msg = "Database connection error: The following required environment variables are not set: " . implode(', ', $missing_vars);
            error_log($error_msg);
            return ['db_error' => $error_msg];
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            $error_msg = "Database connection failed: " . $e->getMessage();
            error_log($error_msg);
            return ['db_error' => $error_msg];
        }
    }
    return $pdo;
}

// ... the rest of the functions (getAllUsers, deleteUserByEmail, storeLotteryResult) remain exactly the same ...
// (You can copy them from your original file or just leave them as they are if you only add the top part)

/**
 * Retrieves all users from the database.
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
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error in deleteUserByEmail for {$email}: " . $e->getMessage());
        return false;
    }
}

/**
 * Stores lottery results into the lottery_results table.
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
        $stmt = $pdo->prepare("SELECT id FROM lottery_results WHERE lottery_type = ? AND issue_number = ?");
        $stmt->execute([$lotteryType, $issueNumber]);
        if ($stmt->fetch()) {
            error_log("Lottery result for type '{$lotteryType}' and issue '{$issueNumber}' already exists. Skipping insertion.");
            return true;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO lottery_results (lottery_type, issue_number, winning_numbers, zodiac_signs, colors, drawing_date) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$lotteryType, $issueNumber, $winningNumbers, $zodiacSigns, $colors, $drawingDate]);
        error_log("Successfully stored lottery result for {$lotteryType} - {$issueNumber}.");
        return true;
    } catch (PDOException $e) {
        error_log("Error storing lottery result for {$lotteryType} - {$issueNumber}: " . $e->getMessage());
        return false;
    }
}
