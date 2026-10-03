<?php

namespace Modules\Auth\app\Actions;

use Illuminate\Support\Str;
use Modules\Auth\app\Enum\UserStatus;
use Modules\Auth\app\Exceptions\UserNotActiveException;
use Modules\Auth\app\Models\PasswordResetToken;
use Modules\Auth\app\Models\User;

class ForgotPasswordAction
{
    /**
     * Checks if the user is active and exists then login the user
     */
    public function handle(string $email, string $ip, string $userAgent): ?array
    {
        $user = User::where('email', $email)->first();

        if ($user) {
            if ($user->status !== UserStatus::ACTIVE) {
                throw new UserNotActiveException($user->status);
            }

            $rawToken = Str::random(60);

            $data = [
                'user_id' => $user->id,
                'token' => hash('sha256', $rawToken),
                'expires_at' => now()->addMinutes(30),
            ];

            PasswordResetToken::where('user_id', $user->id)->delete();

            PasswordResetToken::create($data);

            return [
                'user' => $user,
                'token' => $rawToken,
                'expires_at' => $data['expires_at'],
            ];
        }

        return null;
    }
}
