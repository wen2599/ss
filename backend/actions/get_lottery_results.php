<?php
/**
 * Action: get_lottery_results
 *
 * This script retrieves the single most recent lottery result for each of the major
 * lottery types (香港, 新澳门, 老澳). It is designed to provide a quick overview
 * of the latest results, not a full history.
 *
 * HTTP Method: GET
 *
 * Response:
 * - On success: { "success": true, "results": [ { "id": int, "lottery_name": string, ... } ] }
 * - On error: { "success": false, "error": "Error message." }
 */

// The main router (index.php) handles initialization.
// Global variables $pdo and $log are available.

try {
    // This SQL query uses a Common Table Expression (CTE) to find the most recent
    // result for each lottery type. It's more efficient and scalable than multiple UNIONs.
    // It partitions the data by lottery name and assigns a row number to each record,
    // ordered by date. We then select only the #1 row from each partition.
    $sql = "
        WITH RankedResults AS (
            SELECT
                *,
                ROW_NUMBER() OVER(PARTITION BY
                    CASE
                        WHEN lottery_name LIKE '%香港%' THEN '香港'
                        WHEN lottery_name LIKE '%新澳门%' THEN '新澳门'
                        WHEN lottery_name LIKE '%老澳%' THEN '老澳'
                        ELSE 'other'
                    END
                ORDER BY parsed_at DESC, id DESC) as rn
            FROM lottery_results
            WHERE lottery_name LIKE '%香港%' OR lottery_name LIKE '%新澳门%' OR lottery_name LIKE '%老澳%'
        )
        SELECT id, lottery_name, issue_number, numbers, parsed_at
        FROM RankedResults
        WHERE rn = 1;
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    $log->info("Successfully retrieved latest lottery results.", ['result_count' => count($results)]);
    echo json_encode(['success' => true, 'results' => $results]);

} catch (PDOException $e) {
    // The global exception handler in init.php will catch this.
    $log->error("Database error while retrieving lottery results.", ['error' => $e->getMessage()]);
    throw $e;
}
?>