<?php

// API handler for chart data

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/core/Response.php';

// Handle different HTTP methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // --- Get Chart Data ---
        $dummyData = [
            ['name' => 'Page A', 'uv' => 4000, 'pv' => 2400, 'amt' => 2400],
            ['name' => 'Page B', 'uv' => 3000, 'pv' => 1398, 'amt' => 2210],
            ['name' => 'Page C', 'uv' => 2000, 'pv' => 9800, 'amt' => 2290],
            ['name' => 'Page D', 'uv' => 2780, 'pv' => 3908, 'amt' => 2000],
            ['name' => 'Page E', 'uv' => 1890, 'pv' => 4800, 'amt' => 2181],
            ['name' => 'Page F', 'uv' => 2390, 'pv' => 3800, 'amt' => 2500],
            ['name' => 'Page G', 'uv' => 3490, 'pv' => 4300, 'amt' => 2100],
        ];
        Response::json($dummyData);
        break;

    case 'POST':
        // --- Save Chart Data ---
        $postData = $GLOBALS['requestBody'] ?? null;
        if ($postData) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/data/saved_data.log', json_encode($postData) . PHP_EOL, FILE_APPEND);
            Response::json(['status' => 'success', 'received' => $postData]);
        } else {
            Response::json(['error' => 'No data received'], 400);
        }
        break;

    case 'OPTIONS':
        // Handle preflight requests for CORS
        Response::json(null, 204);
        break;

    default:
        // Method not allowed
        Response::json(['error' => 'Method Not Allowed'], 405);
        break;
}
