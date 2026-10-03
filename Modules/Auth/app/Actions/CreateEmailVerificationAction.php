<?php

namespace Modules\Auth\app\Actions;

use Illuminate\Support\Str;
use Modules\Auth\app\Models\EmailVerification;

class CreateEmailVerificationAction
{
    public function handle(array $data): array
    {

        $verification = EmailVerification::where('user_id', $data['user_id'])->first();

        if ($verification) {
            $verification->delete();
        }

        // unset $verification

        unset($verification);

        $rawToken = Str::random(60);

        $verification = EmailVerification::create([
            'user_id' => $data['user_id'],
            'token' => hash('sha256', $rawToken),
            'expires_at' => $data['expires_at'],
            'last_sent_at' => now(),
        ]);

        return [
            'token' => $rawToken,
            'expires_at' => $data['expires_at'],
        ];
    }
}
