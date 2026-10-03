<?php

namespace Modules\Auth\app\Actions;

use Modules\Auth\app\Exceptions\EmailAlreadyVerifiedException;
use Modules\Auth\app\Exceptions\ExpiredVerificationTokenException;
use Modules\Auth\app\Exceptions\InvalidVerificationTokenException;
use Modules\Auth\app\Models\EmailVerification;
use Modules\Auth\app\Models\User;

class VerifyEmailAction
{
    public function handle(string $tokenHashed): User
    {
        $verification = EmailVerification::where('token', $tokenHashed)->first();

        if (! $verification) {
            throw new InvalidVerificationTokenException;
        }

        $user = $verification->user;

        if ($user->hasVerifiedEmail()) {
            throw new EmailAlreadyVerifiedException;
        }

        if ($verification->expires_at < now()) {
            throw new ExpiredVerificationTokenException;
        }

        $user->update([
            'email_verified_at' => now(),
        ]);

        $verification->update([
            'verified_at' => now(),
        ]);

        $verification->delete();

        return $user;
    }
}
