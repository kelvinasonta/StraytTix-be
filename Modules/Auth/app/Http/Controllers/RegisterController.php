<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Modules\Auth\app\DTOs\RegisterDTO;
use Modules\Auth\app\Http\Requests\RegisterRequest;
use Modules\Auth\app\Services\RegisterService;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request, RegisterService $registerService)
    {
        $registerDto = RegisterDTO::fromArray($request->validated());

        $data = $registerService->execute($registerDto, $request->userAgent());

        return ApiResponse::success('Registration successful', $data, 201);
    }
}
