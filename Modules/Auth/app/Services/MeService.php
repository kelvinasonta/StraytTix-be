<?php

namespace Modules\Auth\app\Services;

use Modules\Auth\app\Models\User;
use Modules\Auth\app\Transformers\UserResource;
use Modules\Organizer\app\Transformers\OrganizerResource;

class MeService
{
    public function execute(User $user): array
    {
        $organizer = $user->ownedOrganizers ?? $user->organizers->first();

        return [
            'user' => new UserResource($user),
            'organizer' => $organizer ? new OrganizerResource($organizer, false, $user) : null,
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }
}
