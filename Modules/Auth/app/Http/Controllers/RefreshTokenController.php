<?php

namespace Modules\Auth\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Modules\Auth\app\Transformers\UserResource;
use Modules\Organizer\app\Transformers\OrganizerResource;

class RefreshTokenController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        // Revoke the current access token
        if ($currentToken) {
            $currentToken->delete();
        }

        // Issue a new token with 2 hours expiration
        $expiresAt = now()->addHours(2);
        $newToken = $user->createToken(
            $request->userAgent() ?? 'eventsOs-client',
            ['*'],
            $expiresAt
        )->plainTextToken;

        $organizer = $user->ownedOrganizers ?? $user->organizers->first();

        return ApiResponse::success('Token refreshed successfully.', [
            'user' => new UserResource($user),
            'organizer' => $organizer ? new OrganizerResource($organizer, false, $user) : null,
            'token' => $newToken,
        ]);
    }
}
