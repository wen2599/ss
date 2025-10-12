<?php

// Set headers for CORS and content type.
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Set a 200 OK status code.
http_response_code(200);

// Create a simple success message.
$response = [
    'status' => 'success',
    'message' => 'The basic PHP environment is working correctly.'
];

// Output the JSON response and terminate the script.
echo json_encode($response);

exit;

