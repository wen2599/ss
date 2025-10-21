<?php
declare(strict_types=1);

use App\Controllers\EmailController;
use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

return function (App $app) {

    // API group
    $app->group('/api', function ($group) {

        // Health check endpoint
        $group->get('/ping', function (Request $request, Response $response) {
            $response->getBody()->write(json_encode(['status' => 'ok', 'message' => 'Backend is running']));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Email routes
        $group->post('/emails', [EmailController::class, 'receiveEmail']);
        $group->get('/emails', [EmailController::class, 'listEmails']);
        $group->get('/emails/{id}', [EmailController::class, 'getEmail']);

    });

    // Catch-all for the root for basic feedback
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write("<h1>Backend API is active</h1><p>Use the /api endpoints to interact.</p>");
        return $response;
    });
};
