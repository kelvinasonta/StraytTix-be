<?php

namespace Modules\Auth\app\Services;

use Modules\Auth\app\Models\User;
use Modules\Auth\app\Transformers\UserResource;
use Modules\Organizer\app\Transformers\OrganizerResource;
use Modules\Shared\app\Services\FeatureFlagService;

class MeService
{
    public function __construct(
        protected FeatureFlagService $featureFlagService
    ) {}

    public function execute(User $user): array
    {
        $organizerIdentifier = request()->header('X-Organizer-UUID')
            ?? request()->header('X-Organizer-ID')
            ?? request()->query('organizer_uuid');

        $organizer = $user->resolveActiveOrganizer(is_string($organizerIdentifier) ? $organizerIdentifier : null);

        return [
            'user' => new UserResource($user),
            'organizer' => $organizer ? new OrganizerResource($organizer, true, $user) : null,
            'email_verified' => $user->hasVerifiedEmail(),
            'features' => $this->featureFlagService->getAllFeatures(),
        ];
    }
}
