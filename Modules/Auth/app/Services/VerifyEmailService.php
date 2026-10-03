<?php

namespace Modules\Auth\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Actions\VerifyEmailAction;
use Modules\Auth\app\DTOs\VerifyEmailDTO;
use Modules\Auth\app\Events\EmailVerifiedEvent;
use Modules\Auth\app\Exceptions\AuthException;
use Modules\Auth\app\Exceptions\VerifyEmailFailedException;
use Modules\Auth\app\Transformers\UserResource;

class VerifyEmailService
{
    public function __construct(private VerifyEmailAction $verifyEmailAction) {}

    public function execute(VerifyEmailDTO $dto): array
    {
        $tokenHashed = hash('sha256', $dto->token);

        try {
            $user = DB::transaction(function () use ($tokenHashed) {
                return $this->verifyEmailAction->handle($tokenHashed);
            });

            $userData = [
                'firstName' => $user->first_name,
                'email' => $user->email,
            ];

            event(new EmailVerifiedEvent($userData));

            $data = [
                'user' => new UserResource($user),
            ];

            return $data;
        } catch (AuthException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Verify email failed: '.$e->getMessage());

            throw new VerifyEmailFailedException;
        }
    }
}
