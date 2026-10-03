<?php

namespace Modules\Auth\app\Services;

use Modules\Auth\app\Actions\LoginUserAction;
use Modules\Auth\app\DTOs\LoginDTO;
use Modules\Auth\app\Transformers\UserResource;
use Modules\Organizer\app\Transformers\OrganizerResource;

class LoginService
{
    public function __construct(
        private LoginUserAction $loginUserAction
    ) {}

    public function execute(LoginDTO $dto, string $ip, string $userAgent): array
    {
        $user = $this->loginUserAction->execute($dto, [
            'ip' => $ip,
            'userAgent' => $userAgent,
        ]);

        $expiresAt = $dto->remember ? now()->addDays(30) : now()->addHours(2);

        $organizer = $user->ownedOrganizers ?? $user->organizers->first();

        return [
            'user' => new UserResource($user),
            'organizer' => $organizer ? new OrganizerResource($organizer, false, $user) : null,
            'token' => $user->createToken(
                $userAgent,
                ['*'],
                $expiresAt
            )->plainTextToken,
        ];
    }
}
