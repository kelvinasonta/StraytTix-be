<?php

namespace Modules\Organizer\app\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizerRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $organizerUuid = $request->route('organizerUuid')
            ?? $request->route('organizer')
            ?? $request->header('X-Organizer-UUID')
            ?? $request->header('X-Organizer-ID');

        if (! $organizerUuid) {
            return ApiResponse::error('Organizer context is required.', 400);
        }

        $organizer = $organizerUuid instanceof Organizer
            ? $organizerUuid
            : Organizer::where('uuid', $organizerUuid)->orWhere('id', $organizerUuid)->first();

        if (! $organizer) {
            return ApiResponse::error('Organizer not found.', 404);
        }

        $userRole = null;
        if ($organizer->user_id === $user->id) {
            $userRole = OrganizerMemberRole::OWNER;
        } else {
            $member = OrganizerMember::where('organizer_id', $organizer->id)
                ->where('user_id', $user->id)
                ->first();
            $userRole = $member?->role;
        }

        if (! $userRole) {
            return ApiResponse::error('You are not a member of this organizer.', 403);
        }

        if (! empty($roles)) {
            $allowedRoles = array_map(fn ($r) => strtolower($r), $roles);
            if (! in_array(strtolower($userRole->value), $allowedRoles)) {
                return ApiResponse::error('You do not have permission to perform this action for this organizer.', 403);
            }
        }

        $request->attributes->set('active_organizer', $organizer);
        $request->attributes->set('active_organizer_role', $userRole);

        return $next($request);
    }
}
