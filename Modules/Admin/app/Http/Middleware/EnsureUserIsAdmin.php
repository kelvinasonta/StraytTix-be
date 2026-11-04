<?php

declare(strict_types=1);

namespace Modules\Admin\app\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Modules\Auth\app\Models\User;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string|null  $permission  Optional permission identifier to enforce
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('Unauthenticated.', 401, 'UNAUTHENTICATED');
        }

        if (! $user->isAdministrator()) {
            return ApiResponse::error(
                'Access denied. Administrator privileges required.',
                403,
                'ADMIN_ACCESS_REQUIRED'
            );
        }

        if ($permission && ! $user->hasAdminPermission($permission)) {
            return ApiResponse::error(
                "Access denied. Missing required administrative permission: {$permission}.",
                403,
                'INSUFFICIENT_ADMIN_PERMISSIONS',
                ['required_permission' => $permission]
            );
        }

        return $next($request);
    }
}
