<?php

declare(strict_types=1);

namespace Modules\Admin\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\User;

class AdminAuthController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Authenticate an administrator and issue an admin-scoped Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $this->auditService->log(
                action: 'admin.login_failed',
                description: 'Failed administrative login attempt for email: '.$validated['email'],
                metadata: ['email' => $validated['email'], 'ip' => $request->ip()]
            );

            return ApiResponse::error(
                'Invalid credentials.',
                401,
                'INVALID_CREDENTIALS'
            );
        }

        if (! $user->isAdministrator()) {
            $this->auditService->log(
                action: 'admin.unauthorized_login_attempt',
                description: "Non-admin user [{$user->id}] attempted administrative login.",
                user: $user,
                metadata: ['ip' => $request->ip()]
            );

            return ApiResponse::error(
                'Access denied. Account does not have administrative privileges.',
                403,
                'ADMIN_ACCESS_REQUIRED'
            );
        }

        // Revoke previous admin tokens if single session preferred or create new
        $deviceName = $validated['device_name'] ?? 'Admin Dashboard';
        $token = $user->createToken($deviceName, ['admin:access'])->plainTextToken;

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $this->auditService->log(
            action: 'admin.login_success',
            description: "Administrator [{$user->id}] logged in successfully.",
            user: $user,
            metadata: ['device' => $deviceName, 'ip' => $request->ip()]
        );

        return ApiResponse::success('Administrative login successful.', [
            'token' => $token,
            'token_type' => 'Bearer',
            'admin' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'admin_role' => $user->admin_role,
                'permissions' => $user->admin_permissions ?? [],
            ],
        ]);
    }

    /**
     * Get the authenticated administrator's profile and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success('Administrator profile retrieved.', [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'admin_role' => $user->admin_role,
            'permissions' => $user->admin_permissions ?? [],
            'status' => $user->status instanceof \BackedEnum ? $user->status->value : (string) $user->status,
            'last_login_at' => $user->last_login_at?->toISOString(),
        ]);
    }

    /**
     * Terminate the administrative session by revoking the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke the current access token
        $user->currentAccessToken()?->delete();

        $this->auditService->log(
            action: 'admin.logout',
            description: "Administrator [{$user->id}] logged out.",
            user: $user,
            metadata: ['ip' => $request->ip()]
        );

        return ApiResponse::success('Administrative session terminated successfully.');
    }
}
