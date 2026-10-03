<?php

namespace Modules\Auth\app\Actions;

use Modules\Auth\app\DTOs\ResetPasswordDTO;
use Modules\Auth\app\Enum\UserStatus;
use Modules\Auth\app\Exceptions\ExpiredResetTokenException;
use Modules\Auth\app\Exceptions\InvalidResetTokenException;
use Modules\Auth\app\Exceptions\UserNotActiveException;
use Modules\Auth\app\Exceptions\UserNotFoundException;
use Modules\Auth\app\Models\PasswordResetToken;
use Modules\Auth\app\Models\User;

class ResetPasswordAction
{
    public function handle(ResetPasswordDTO $dto): User
    {
        $hashedToken = hash('sha256', $dto->token);

        $resetToken = PasswordResetToken::where('token', $hashedToken)->first();

        if (! $resetToken) {
            throw new InvalidResetTokenException;
        }

        if ($resetToken->expires_at && $resetToken->expires_at->isPast()) {
            $resetToken->delete();

            throw new ExpiredResetTokenException;
        }

        $user = User::find($resetToken->user_id);

        if (! $user) {
            $resetToken->delete();

            throw new UserNotFoundException;
        }

        if ($user->status !== UserStatus::ACTIVE) {
            throw new UserNotActiveException($user->status);
        }

        $user->password = $dto->password;
        $user->save();

        $resetToken->delete();

        // Revoke active API tokens for security
        $user->tokens()->delete();

        return $user;
    }
}
