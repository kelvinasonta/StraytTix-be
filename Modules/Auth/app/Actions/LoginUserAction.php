<?php

namespace Modules\Auth\app\Actions;

use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\DTOs\LoginDTO;
use Modules\Auth\app\Enum\UserStatus;
use Modules\Auth\app\Events\LoginAttemptEvent;
use Modules\Auth\app\Exceptions\InvalidCredentialsException;
use Modules\Auth\app\Exceptions\UserNotActiveException;
use Modules\Auth\app\Models\User;

class LoginUserAction
{
    /**
     * Checks if the user is active and exists then login the user
     */
    public function execute(LoginDTO $dto, array $meta): User
    {
        $user = User::where('email', $dto->email)->first();

        if (! $user) {

            $this->logLoginAttempt(null, $dto->email, false, $meta['ip'], $meta['userAgent']);

            throw new InvalidCredentialsException;
        }

        if ($user->status !== UserStatus::ACTIVE) {
            throw new UserNotActiveException($user->status);
        }

        if (! Hash::check($dto->password, $user->password)) {

            $this->logLoginAttempt($user, $dto->email, false, $meta['ip'], $meta['userAgent']);

            throw new InvalidCredentialsException;
        }

        $this->logLoginAttempt($user, $dto->email, true, $meta['ip'], $meta['userAgent']);

        return $user;
    }

    private function logLoginAttempt(?User $user, string $email, bool $success, string $ip, string $userAgent): void
    {
        LoginAttemptEvent::dispatch(
            $user,
            $email,
            $success,
            $ip,
            $userAgent
        );
    }
}
