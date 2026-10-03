<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Modules\Auth\app\DTOs\LoginDTO;
use Modules\Auth\app\Http\Requests\LoginRequest;
use Modules\Auth\app\Services\LoginService;

class LoginController extends Controller
{
    public function store(LoginRequest $request, LoginService $loginService)
    {
        $dto = LoginDTO::fromArray($request->validated());

        $data = $loginService->execute($dto, $request->ip(), $request->userAgent());

        return ApiResponse::success('Login successful', $data, 200);
    }
}
