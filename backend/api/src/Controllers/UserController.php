<?php
declare(strict_types=1);

namespace App\Controllers;

// Final test: This class is now standalone and does NOT extend BaseController.
class UserController
{
    /**
     * This method is copied directly from BaseController to make this class independent.
     */
    protected function jsonResponse(int $statusCode, array $data): void
    {
        // We avoid calling http_response_code here as a further simplification, 
        // relying on the webserver to handle the 200 default.
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * This is the target method for our /api/ping test.
     */
    public function ping(): void
    {
        $this->jsonResponse(200, ['status' => 'success', 'data' => 'Backend is running (Standalone UserController)']);
    }

    // All other methods are still absent for this test.
}
