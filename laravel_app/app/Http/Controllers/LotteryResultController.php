<?php

namespace App\Http\Controllers;

use App\Models\LotteryResult;
use Illuminate\Http\Request;

class LotteryResultController extends Controller
{
    /**
     * Get the latest lottery results.
     */
    public function index(Request $request)
    {
        $results = LotteryResult::orderBy('draw_date', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $results,
        ]);
    }
}
