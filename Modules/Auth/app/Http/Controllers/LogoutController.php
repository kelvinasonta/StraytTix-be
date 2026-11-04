<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();

            return ApiResponse::success('Logged out successfully.');
        }

        return ApiResponse::error('No authenticated user found.', 401, 'UNAUTHENTICATED');
    }
}
