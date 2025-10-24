<?php
declare(strict_types=1);

namespace App\Controllers;

// This is a minimal version for debugging. 
// It inherits the ping() method from BaseController.
class UserController extends BaseController
{
    // All other methods (register, login, etc.) are temporarily removed 
    // to isolate the source of the 502 error.
}
