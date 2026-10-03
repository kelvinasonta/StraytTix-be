<?php

declare(strict_types=1);

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\LoginAttempts;

class SessionController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all active sessions (access tokens) for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $tokens = $user->tokens()->latest()->get()->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'lastUsedAt' => $token->last_used_at?->toISOString(),
                'createdAt' => $token->created_at?->toISOString(),
                'isCurrent' => $token->id === $currentTokenId,
            ];
        });

        return ApiResponse::success('Active sessions retrieved.', $tokens);
    }

    /**
     * Revoke a specific active session.
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();
        $token = $user->tokens()->where('id', $id)->first();

        if (! $token) {
            return ApiResponse::error('Session not found.', 404);
        }

        $tokenName = $token->name;
        $tokenId = $token->id;

        $token->delete();

        $this->auditService->log(
            action: 'user.session.revoked',
            description: "Session '{$tokenName}' was revoked for {$user->email}",
            organizer: null,
            user: $user,
            metadata: [
                'token_id' => $tokenId,
                'token_name' => $tokenName,
            ]
        );

        return ApiResponse::success('Session revoked successfully.');
    }

    /**
     * Revoke all other active sessions except the current one.
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();

            $this->auditService->log(
                action: 'user.sessions.revoked_others',
                description: "All other sessions were revoked for {$user->email}",
                organizer: null,
                user: $user
            );
        }

        return ApiResponse::success('Logged out of all other devices successfully.');
    }

    /**
     * Get paginated login history (all past login attempts and locations) for the user.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $attempts = LoginAttempts::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('email', $user->email);
        })
            ->latest('id')
            ->paginate($perPage);

        $transformed = $attempts->through(function ($attempt) {
            $metadata = is_array($attempt->metadata) ? $attempt->metadata : [];

            return [
                'id' => $attempt->id,
                'ipAddress' => $attempt->ip_address,
                'device' => $metadata['device'] ?? null,
                'os' => $metadata['os'] ?? null,
                'browser' => $metadata['browser'] ?? null,
                'city' => $metadata['city'] ?? null,
                'country' => $metadata['country'] ?? null,
                'status' => (bool) $attempt->status,
                'createdAt' => $attempt->created_at?->toISOString(),
            ];
        });

        return ApiResponse::success('Login history retrieved successfully.', $transformed);
    }
}
