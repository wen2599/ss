<?php
// This is a router script for the PHP built-in web server.
// It emulates the behavior of mod_rewrite to support clean URLs.

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// This is the document root.
$publicDir = __DIR__;

// If the request is for a static file that exists in the filesystem, serve it directly.
// This is important for CSS, JS, images, etc.
if ($uri !== '/' && file_exists($publicDir . $uri)) {
    return false;
}

// Handle backend API requests.
// Any request starting with /backend/ is treated as an API call.
if (strpos($uri, '/backend/') === 0) {
    // Extract the action from the URI.
    // e.g., /backend/get_lottery_results -> get_lottery_results
    $action = substr($uri, strlen('/backend/'));

    // Set the 'action' parameter for the main index.php script.
    $_GET['action'] = $action;

    // Include the main backend entry point to handle the logic.
    require_once $publicDir . '/backend/index.php';

    // Return true to indicate that the request has been handled.
    return true;
}

// For all other requests, serve the main frontend application entry point.
// This allows client-side routing (like React Router) to take over.
// We check for the built version of the frontend.
if (file_exists($publicDir . '/frontend/dist/index.html')) {
    require_once $publicDir . '/frontend/dist/index.html';
    return true;
}

// If no specific route or file is found, let the server return a 404.
return false;
?>