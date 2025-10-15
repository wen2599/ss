<?php
// A simple script to test outgoing cURL from the web server.

echo "<h1>cURL Test</h1>";
echo "<p>Attempting to fetch content from https://example.com...</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://example.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 second timeout
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

echo "cURL initialized. Executing request...<br>";

$output = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

echo "Request executed. Closing cURL session...<br>";

curl_close($ch);

echo "<hr>";

if ($output === false) {
    echo "<h2>Result: FAILED</h2>";
    echo "<p>cURL execution failed.</p>";
    echo "<p><b>Error Message:</b> <pre>" . htmlspecialchars($error) . "</pre></p>";
    echo "<p><b>This strongly suggests your hosting provider is blocking outgoing cURL requests from the web server. Please contact them for assistance.</b></p>";
} else {
    echo "<h2>Result: SUCCESS!</h2>";
    echo "<p><b>HTTP Status Code:</b> {$http_code}</p>";
    echo "<p>Successfully received a response from example.com.</p>";
    echo "<p><b>Response Content (first 200 chars):</b></p>";
    echo "<pre>" . htmlspecialchars(substr($output, 0, 200)) . "...</pre>";
}
?>