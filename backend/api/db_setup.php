<?php
// backend/api/db_setup.php

require_once 'config.php';

try {
    // Create a new PDO instance
    $pdo = new PDO('sqlite:' . DB_PATH);

    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL to create table
    $sql = "
    CREATE TABLE IF NOT EXISTS draws (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        period INTEGER NOT NULL,
        date TEXT NOT NULL,
        numbers TEXT NOT NULL,
        specialNumber INTEGER NOT NULL
    );";

    // Execute SQL
    $pdo->exec($sql);

    echo "Table 'draws' created successfully.\n";

    // --- Data Insertion ---
    $draws = [
      [
        'period' => 2024088,
        'date' => '2024-08-03',
        'numbers' => json_encode([3, 12, 18, 25, 33, 41]),
        'specialNumber' => 49,
      ],
      [
        'period' => 2024087,
        'date' => '2024-08-01',
        'numbers' => json_encode([7, 11, 20, 34, 38, 45]),
        'specialNumber' => 22,
      ],
      [
        'period' => 2024086,
        'date' => '2024-07-30',
        'numbers' => json_encode([5, 16, 21, 29, 40, 47]),
        'specialNumber' => 10,
      ],
      [
        'period' => 2024085,
        'date' => '2024-07-27',
        'numbers' => json_encode([1, 9, 15, 23, 30, 44]),
        'specialNumber' => 37,
      ],
    ];

    $stmt = $pdo->prepare("INSERT INTO draws (period, date, numbers, specialNumber) VALUES (:period, :date, :numbers, :specialNumber)");

    foreach ($draws as $draw) {
        $stmt->execute($draw);
    }

    echo "Data inserted successfully.\n";

} catch (PDOException $e) {
    die("Could not connect to the database " . DB_PATH . ": " . $e->getMessage());
}
?>
