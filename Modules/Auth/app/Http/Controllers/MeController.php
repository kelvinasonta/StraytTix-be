<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Modules\Auth\app\Services\MeService;

class MeController extends Controller
{
    /**
     * Display the authenticated user's information.
     */
    public function __invoke(Request $request, MeService $meService)
    {
        $data = $meService->execute($request->user());

        return ApiResponse::success('User data fetched successfully', $data, 200);
    }
}
