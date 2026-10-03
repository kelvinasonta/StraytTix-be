<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;

class LogoutController extends Controller
{
    public function store()
    {
        // Invalidate api token
        if (auth()->user()) {
            auth()->user()->currentAccessToken()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Logged out successfully.',
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No authenticated user found.',
        ], 401);

    }
}
