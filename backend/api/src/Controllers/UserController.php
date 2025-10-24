<?php
declare(strict_types=1);

namespace App\Controllers;

// This is a minimal, non-functional version for debugging purposes.
class UserController extends BaseController
{
    // We add a dummy ping method so the router can find it.
    public function ping(): void
    {
        // This will not be executed because our debug index.php stops before instantiation.
    }
}
