<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Modules\Auth\app\DTOs\ResetPasswordDTO;
use Modules\Auth\app\Http\Requests\ResetPasswordRequest;
use Modules\Auth\app\Services\ResetPasswordService;

class ResetPasswordController extends Controller
{
    public function store(ResetPasswordRequest $request, ResetPasswordService $resetPasswordService)
    {
        $dto = ResetPasswordDTO::fromArray($request->validated());

        $resetPasswordService->execute($dto, $request->ip(), $request->userAgent());

        return ApiResponse::success('Password reset successful.', [], 200);
    }
}
