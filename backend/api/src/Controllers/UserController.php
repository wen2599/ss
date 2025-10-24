<?php
declare(strict_types=1);

namespace App\Controllers;

class UserController extends BaseController
{
    // This is a temporary, simplified version for debugging.
    // All original methods (register, login, logout, isRegistered) are removed.
    // The 'ping' method is handled directly by the router in index.php for this test phase.
    // The purpose is to check if merely loading this file (and its parent BaseController)
    // causes a fatal error.

    public function ping(): void
    {
        // This method will actually be called if we pass the autoloader/class_exists check.
        // It's a placeholder to satisfy the router for /api/ping.
        $this->jsonResponse(200, ['status' => 'success', 'message' => 'Ping response from simplified UserController.']);
    }
}
