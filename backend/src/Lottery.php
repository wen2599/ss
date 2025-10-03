<?php

namespace App;

use PDO;
use PDOException;

/**
 * Class Lottery
 *
 * A library class for handling lottery-related database operations, such as
 * saving parsed lottery results.
 */
class Lottery {

    /**
     * Saves a parsed lottery result to the database. If a result for the same
     * lottery name and issue number already exists, it will be updated.
     *
     * @param PDO $pdo The database connection object.
     * @param array $result The parsed result array from LotteryParser. It must contain
     *                      'lottery_name', 'issue_number', and 'numbers' (as an array).
     * @return string A status message indicating the outcome (inserted, updated, or unchanged).
     * @throws PDOException If a database error occurs during the operation.
     */
    public static function saveLotteryResultToDB(PDO $pdo, array $result): string
    {
        global $log; // Use the global logger from init.php

        $numbersStr = implode(',', $result['numbers']);

        $sql = "INSERT INTO lottery_results (lottery_name, issue_number, numbers)
                VALUES (:lottery_name, :issue_number, :numbers)
                ON DUPLICATE KEY UPDATE numbers = VALUES(numbers), parsed_at = CURRENT_TIMESTAMP";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':lottery_name' => $result['lottery_name'],
                ':issue_number' => $result['issue_number'],
                ':numbers' => $numbersStr
            ]);

            // In MySQL with ON DUPLICATE KEY UPDATE, rowCount() returns:
            // 0 if no change was made (the record was identical).
            // 1 if a new row was inserted.
            // 2 if an existing row was updated.
            $rowCount = $stmt->rowCount();

            if ($rowCount === 1) {
                $log->info("New lottery result saved.", ['result' => $result]);
                return "新开奖结果已成功存入数据库。";
            } elseif ($rowCount === 2) {
                $log->info("Lottery result updated.", ['result' => $result]);
                return "开奖结果已在数据库中更新。";
            } else {
                $log->info("Lottery result was unchanged.", ['result' => $result]);
                return "开奖结果与数据库记录一致，未作更改。";
            }
        } catch (PDOException $e) {
            $log->error("Database error saving lottery result.", [
                'error' => $e->getMessage(),
                'result' => $result
            ]);
            // Re-throw the exception to be handled by the global error handler.
            throw $e;
        }
    }
}
?>