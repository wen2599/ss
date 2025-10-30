<?php
// backend/helpers.php

function sendJsonResponse($data, $statusCode = 200) {
    // Set CORS headers to allow requests from any origin.
    // In a production environment, you should restrict this to your frontend's domain.
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204); // No Content
        exit;
    }

    // Set JSON content type and response code
    header('Content-Type: application/json');
    http_response_code($statusCode);

    // Encode and output the data
    echo json_encode($data);

    // Terminate script to prevent further output
    exit;
}
