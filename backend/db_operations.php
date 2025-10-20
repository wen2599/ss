<?php
// backend/db_operations.php
// Contains common database operation functions.

/**
 * Executes a prepared statement with given parameters.
 * @param PDO $pdo The PDO database connection object.
 * @param string $sql The SQL query string.
 * @param array $params An associative array of parameters for the prepared statement.
 * @return PDOStatement The executed PDOStatement object.
 * @throws PDOException If the query fails.
 */
function executeStatement(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    if (!$stmt->execute($params)) {
        throw new PDOException("Statement execution failed: " . implode(", ", $stmt->errorInfo()));
    }
    return $stmt;
}

/**
 * Fetches a single row from the database.
 * @param PDO $pdo The PDO database connection object.
 * @param string $sql The SQL query string.
 * @param array $params An associative array of parameters for the prepared statement.
 * @return array|false An associative array of the row, or false if no row is found.
 */
function fetchOne(PDO $pdo, string $sql, array $params = []): array|false
{
    $stmt = executeStatement($pdo, $sql, $params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fetches all rows from the database.
 * @param PDO $pdo The PDO database connection object.
 * @param string $sql The SQL query string.
 * @param array $params An associative array of parameters for the prepared statement.
 * @return array An array of associative arrays, each representing a row.
 */
function fetchAll(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = executeStatement($pdo, $sql, $params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Inserts a new row into the database.
 * @param PDO $pdo The PDO database connection object.
 * @param string $table The name of the table to insert into.
 * @param array $data An associative array of column_name => value.
 * @return int The ID of the last inserted row.
 * @throws PDOException If the insertion fails.
 */
function insert(PDO $pdo, string $table, array $data): int
{
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
    executeStatement($pdo, $sql, $data);
    return (int)$pdo->lastInsertId();
}

/**
 * Updates rows in the database.
 * @param PDO $pdo The PDO database connection object.
 * @param string $table The name of the table to update.
 * @param array $data An associative array of column_name => value to set.
 * @param string $whereClause The WHERE clause for the update statement (e.g., 'id = :id').
 * @param array $whereParams An associative array of parameters for the WHERE clause.
 * @return int The number of affected rows.
 * @throws PDOException If the update fails.
 */
function update(PDO $pdo, string $table, array $data, string $whereClause, array $whereParams = []): int
{
    $setParts = [];
    foreach ($data as $key => $value) {
        $setParts[] = "`$key` = :set_$key";
    }
    $setSql = implode(', ', $setParts);

    $sql = "UPDATE `$table` SET $setSql WHERE $whereClause";

    $params = [];
    foreach ($data as $key => $value) {
        $params[":set_$key"] = $value;
    }
    $params = array_merge($params, $whereParams);

    $stmt = executeStatement($pdo, $sql, $params);
    return $stmt->rowCount();
}

/**
 * Deletes rows from the database.
 * @param PDO $pdo The PDO database connection object.
 * @param string $table The name of the table to delete from.
 * @param string $whereClause The WHERE clause for the delete statement (e.g., 'id = :id').
 * @param array $whereParams An associative array of parameters for the WHERE clause.
 * @return int The number of affected rows.
 * @throws PDOException If the deletion fails.
 */
function delete(PDO $pdo, string $table, string $whereClause, array $whereParams = []): int
{
    $sql = "DELETE FROM `$table` WHERE $whereClause";
    $stmt = executeStatement($pdo, $sql, $whereParams);
    return $stmt->rowCount();
}
