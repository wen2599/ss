<?php
// backend/api_curl_helper.php
// Contains helper functions for making HTTP requests using cURL.

/**
 * Sends a POST request using cURL.
 * @param string $url The URL to send the request to.
 * @param array $data The data to send in the request body (will be JSON encoded).
 * @param array $headers Optional: an array of custom headers.
 * @return array The decoded JSON response.
 */
function postRequest(string $url, array $data, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($data))
    ], $headers));
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("cURL POST Error: " . curl_error($ch));
        return ['ok' => false, 'description' => curl_error($ch)];
    }
    curl_close($ch);

    return json_decode($response, true) ?? ['ok' => false, 'description' => 'Invalid JSON response'];
}

/**
 * Sends a GET request using cURL.
 * @param string $url The URL to send the request to.
 * @param array $headers Optional: an array of custom headers.
 * @return array The decoded JSON response.
 */
function getRequest(string $url, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("cURL GET Error: " . curl_error($ch));
        return ['ok' => false, 'description' => curl_error($ch)];
    }
    curl_close($ch);

    return json_decode($response, true) ?? ['ok' => false, 'description' => 'Invalid JSON response'];
}
