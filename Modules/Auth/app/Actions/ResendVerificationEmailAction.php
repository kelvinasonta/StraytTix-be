<?php

namespace Modules\Auth\app\Actions;

use Modules\Auth\app\Models\EmailVerification;

class ResendVerificationEmailAction
{
    public function handle($userId)
    {
        // Check for existing verfication token

        $verification = EmailVerification::where('user_id', $userId)->first();

        if ($verification) {
            $verification->delete();
        }
    }
}
