<?php

namespace Modules\Auth\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Actions\CreateEmailVerificationAction;
use Modules\Auth\app\Events\ResendEmailVerificationEvent;
use Modules\Auth\app\Exceptions\AuthException;
use Modules\Auth\app\Exceptions\ResendEmailVerificationFailedException;
use Modules\Auth\app\Models\User;

class ResendEmailVerificationService
{
    public function __construct(
        private CreateEmailVerificationAction $createEmailVerification,
    ) {}

    public function execute(User $user)
    {

        if ($user->email_verified_at) {
            throw new ResendEmailVerificationFailedException('Email already verified', 422, 'EMAIL_ALREADY_VERIFIED');
        }

        $lastSentTime = $user->emailVerifications()
            ->latest('last_sent_at')
            ->first()
            ?->last_sent_at;

        if ($lastSentTime && $lastSentTime->addMinutes(15) > now()) {
            throw new ResendEmailVerificationFailedException('You can only resend verification email every 15 minutes', 422, 'RESEND_EMAIL_VERIFICATION_FAILED_TOO_SOON');
        }

        try {
            $result = DB::transaction(function () use ($user) {
                return $this->createEmailVerification->handle([
                    'user_id' => $user->id,
                    'expires_at' => now()->addMinutes(30),
                ]);
            });

            $emailData = [
                'email' => $user->email,
                'firstName' => $user->first_name,
                'token' => $result['token'],
                'expires_at' => $result['expires_at'],
            ];

            event(new ResendEmailVerificationEvent($emailData));
        } catch (AuthException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Resend email verification failed: '.$e->getMessage());

            throw new ResendEmailVerificationFailedException;
        }
    }
}
