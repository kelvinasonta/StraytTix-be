<?php

namespace Modules\Organizer\app\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Modules\Organizer\app\Models\Organizer;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizerRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $organizerIdentifier = $request->route('organizerUuid')
            ?? $request->route('organizer')
            ?? $request->header('X-Organizer-UUID')
            ?? $request->header('X-Organizer-ID');

        if (! $organizerIdentifier) {
            return ApiResponse::error('Organizer context is required.', 400);
        }

        $existingOrganizer = $request->attributes->get('active_organizer');
        if (
            $existingOrganizer instanceof Organizer
            && (
                $existingOrganizer->uuid === (string) $organizerIdentifier
                || $existingOrganizer->slug === (string) $organizerIdentifier
                || (string) $existingOrganizer->id === (string) $organizerIdentifier
            )
        ) {
            $organizer = $existingOrganizer;
        } elseif ($organizerIdentifier instanceof Organizer) {
            $organizer = $organizerIdentifier;
        } else {
            $organizer = Organizer::with(['members'])
                ->where('uuid', $organizerIdentifier)
                ->orWhere('slug', $organizerIdentifier)
                ->orWhere('id', $organizerIdentifier)
                ->first();
        }

        if (! $organizer) {
            return ApiResponse::error('Organizer not found.', 404);
        }

        $userRole = $organizer->getRoleForUser($user);

        if (! $userRole) {
            return ApiResponse::error('You are not a member of this organizer.', 403);
        }

        if (! empty($roles)) {
            $allowedRoles = array_map(static fn (string $r): string => strtolower(trim($r)), $roles);
            $hasAccess = in_array(strtolower($userRole->value), $allowedRoles, true);

            if (! $hasAccess) {
                foreach ($roles as $permission) {
                    if ($userRole->hasPermission(trim($permission))) {
                        $hasAccess = true;
                        break;
                    }
                }
            }

            if (! $hasAccess) {
                return ApiResponse::error('You do not have permission to perform this action for this organizer.', 403);
            }
        }

        $request->attributes->set('active_organizer', $organizer);
        $request->attributes->set('active_organizer_role', $userRole);
        $request->attributes->set('active_organizer_permissions', $userRole->permissions());

        return $next($request);
    }
}
