<?php
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Origin: http://localhost:5173");

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    echo json_encode([
        'loggedin' => true,
        'user' => ['username' => $_SESSION['username']]
    ]);
} else {
    echo json_encode(['loggedin' => false]);
}
?>