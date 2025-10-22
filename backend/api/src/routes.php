<?php
declare(strict_types=1);

use App\Controllers\EmailController;
use App\Controllers\UserController;
use App\Models\LotteryNumber;
use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

return function (App $app) {

    // API group
    $app->group('/api', function ($group) {

        // Health check endpoint
        $group->get('/ping', function (Request $request, Response $response) {
            $payload = json_encode(['status' => 'success', 'data' => 'Backend is running']);
            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Email routes
        $group->post('/emails', [EmailController::class, 'receiveEmail']);
        $group->get('/emails', [EmailController::class, 'listEmails']);
        $group->get('/emails/{id}', [EmailController::class, 'getEmail']);

        // User routes
        $group->post('/register', [UserController::class, 'register']);
        $group->post('/login', [UserController::class, 'login']);
        $group->post('/logout', [UserController::class, 'logout']);
        $group->get('/users/is-registered', [UserController::class, 'isUserRegistered']);

        // Lottery routes
        $group->get('/latest-lottery', function (Request $request, Response $response) {
            $latestLottery = LotteryNumber::orderByDesc('draw_time')->first();

            if ($latestLottery) {
                $payload = json_encode([
                    'status' => 'success',
                    'data' => [
                        'id' => $latestLottery->id,
                        'numbers' => $latestLottery->numbers,
                        'draw_time' => $latestLottery->draw_time->toDateTimeString(),
                    ],
                ]);
            } else {
                $payload = json_encode(['status' => 'success', 'data' => null, 'message' => 'No lottery numbers found.']);
            }

            $response->getBody()->write($payload);
            return $response->withHeader('Content-Type', 'application/json');
        });

    });

    // Catch-all for the root for basic feedback
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write("<h1>Backend API is active</h1><p>Use the /api endpoints to interact.</p>");
        return $response;
    });
};
