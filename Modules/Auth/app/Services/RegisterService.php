<?php

namespace Modules\Auth\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Actions\CreateEmailVerificationAction;
use Modules\Auth\app\Actions\RegisterUserAction;
use Modules\Auth\app\DTOs\RegisterDTO;
use Modules\Auth\app\Events\UserRegisteredEvent;
use Modules\Auth\app\Exceptions\AuthException;
use Modules\Auth\app\Exceptions\RegistrationFailedException;
use Modules\Auth\app\Transformers\UserResource;
use Modules\Organizer\app\Actions\CreateOrganizerAction;
use Modules\Organizer\app\Transformers\OrganizerResource;

class RegisterService
{
    public function __construct(
        private RegisterUserAction $registerUserAction,
        private CreateEmailVerificationAction $createEmailVerificationAction,
        private CreateOrganizerAction $createOrganizerAction,
        private AuditService $auditService,
    ) {}

    /**
     * Handle the user registration process.
     */
    public function execute(RegisterDTO $registerDto, string $userAgent): array
    {

        try {

            $success = DB::transaction(function () use ($registerDto) {

                $user = $this->registerUserAction->handle($registerDto);

                $organizer = null;

                if ($user->account_type == 'organizer') {
                    $organizer = $this->createOrganizerAction->handle($user, $registerDto);
                }

                // Email verification Feature Flag

                $result = $this->createEmailVerificationAction->handle([
                    'user_id' => $user->id,
                    'expires_at' => now()->addMinutes(30),
                ]);

                return [
                    'user' => $user,
                    'organizer' => $organizer ?? null,
                    'token' => $result['token'] ?? null,
                    'expires_at' => $result['expires_at'] ?? null,
                ];
            });

            $user = $success['user'];
            $organizer = $success['organizer'];

            $userData = [
                'firstName' => $user->first_name,
                'email' => $user->email,
                'token' => $success['token'],
                'expires_at' => $success['expires_at'],
            ];

            event(new UserRegisteredEvent($userData));

            $expiresAt = now()->addHours(2);

            $this->auditService->log(
                action: 'user.registered',
                description: "Account registered successfully for {$user->email}",
                organizer: $organizer,
                user: $user,
                metadata: [
                    'account_type' => $user->account_type,
                    'user_agent' => $userAgent,
                ]
            );

            return [
                'user' => new UserResource($user),
                'token' => $user->createToken(
                    $userAgent,
                    ['*'],
                    $expiresAt
                )->plainTextToken,
                'organizer' => $organizer ? new OrganizerResource($organizer, false, $user) : null,
            ];
        } catch (AuthException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Registration failed: '.$e->getMessage());
            throw new RegistrationFailedException;
        }
    }
}
