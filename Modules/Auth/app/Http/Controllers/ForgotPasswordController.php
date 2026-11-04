<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Auth\app\Http\Requests\ForgetPasswordRequest;
use Modules\Auth\app\Services\ForgotPasswordService;

class ForgotPasswordController extends Controller
{
    public function store(ForgetPasswordRequest $request, ForgotPasswordService $forgotPasswordService): JsonResponse
    {
        $validated = $request->validated();

        $forgotPasswordService->execute($validated['email'], $request->ip(), $request->userAgent());

        return ApiResponse::success('Forgot password email sent successfully', [], 200);
    }
}
