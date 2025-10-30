<?php
$dotenv = parse_ini_file('.env');
$pdo = new PDO(
    "mysql:host={$dotenv['DB_HOST']};dbname={$dotenv['DB_NAME']}",
    $dotenv['DB_USER'],
    $dotenv['DB_PASS']
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);