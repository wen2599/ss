<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillController extends Controller
{
    /**
     * Get the bills for the authenticated user.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $bills = $user->bills()->orderBy('received_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $bills,
        ]);
    }
}
