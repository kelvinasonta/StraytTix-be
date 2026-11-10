<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Audit\app\Services\AuditService;

class LogoutController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $token = $user->currentAccessToken();
            $tokenName = $token?->name ?? 'api';
            $token?->delete();

            $this->auditService->log(
                action: 'user.logout',
                description: "User [{$user->id}] logged out successfully.",
                user: $user,
                metadata: [
                    'token_name' => $tokenName,
                ]
            );

            return ApiResponse::success('Logged out successfully.');
        }

        return ApiResponse::error('No authenticated user found.', 401, 'UNAUTHENTICATED');
    }
}
