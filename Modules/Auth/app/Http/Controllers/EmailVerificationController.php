<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\app\DTOs\VerifyEmailDTO;
use Modules\Auth\app\Http\Requests\TokenRequest;
use Modules\Auth\app\Services\ResendEmailVerificationService;
use Modules\Auth\app\Services\VerifyEmailService;

class EmailVerificationController extends Controller
{
    public function store(TokenRequest $request, VerifyEmailService $verifyEmailService): JsonResponse
    {
        $dto = VerifyEmailDTO::fromArray($request->validated());

        $data = $verifyEmailService->execute($dto);

        return ApiResponse::success('Email verified successfully', $data, 200);
    }

    public function resend(Request $request, ResendEmailVerificationService $resendEmailVerification): JsonResponse
    {
        $resendEmailVerification->execute($request->user());

        return ApiResponse::success('Verification email sent successfully', [], 200);
    }
}
