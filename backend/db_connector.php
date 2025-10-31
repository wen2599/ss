<?php
// backend/db_connector.php

function get_db_connection() {
    // --- TEMPORARY DIAGNOSTIC CODE ---
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    if (!function_exists('load_env')) {
        die('DIAGNOSTIC_ERROR: The env_loader.php file was not included, or the load_env() function does not exist.');
    }

    $env = load_env();

    if (empty($env)) {
        die('DIAGNOSTIC_ERROR: load_env() was called but returned an empty array. Check file permissions or path for .env file.');
    }

    $host    = $env['DB_HOST'] ?? null;
    $db_name = $env['DB_NAME'] ?? null;
    $user    = $env['DB_USER'] ?? null;
    $pass    = $env['DB_PASSWORD'] ?? null;

    if (!$host || !$db_name || !$user) {
        echo "<pre>";
        echo "DIAGNOSTIC_ERROR: One or more essential database variables could not be found after loading the environment.\n";
        echo "HOST: " . var_export($host, true) . "\n";
        echo "DB_NAME: " . var_export($db_name, true) . "\n";
        echo "USER: " . var_export($user, true) . "\n";
        echo "LOADED ENV ARRAY:\n";
        print_r($env);
        echo "</pre>";
        die();
    }

    $dsn = "mysql:host={$host};dbname={$db_name};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        // This is the final point of failure we are testing.
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // If this message is shown, the problem is 100% the connection itself or the PDO driver.
        die("FINAL DIAGNOSTIC ERROR: PDO Connection Failed: " . $e->getMessage());
    }
}
