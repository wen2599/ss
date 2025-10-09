<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Function to get the database connection
function get_db_connection() {
    $host = DB_HOST;
    $db   = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}

try {
    $pdo = get_db_connection();

    // The SQL query to get the latest result for each lottery type
    $sql = "
        SELECT lr.*
        FROM lottery_results lr
        INNER JOIN (
            SELECT lottery_type, MAX(drawn_at) AS max_drawn_at
            FROM lottery_results
            GROUP BY lottery_type
        ) AS latest ON lr.lottery_type = latest.lottery_type AND lr.drawn_at = latest.max_drawn_at
    ";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();

    send_json_response($results);

} catch (\PDOException $e) {
    // In case of an error, send a 500 status code and an error message
    http_response_code(500);
    send_json_response(['error' => 'Could not retrieve lottery results: ' . $e->getMessage()]);
}
?>