<?php

namespace Modules\Auth\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\DTOs\PasswordUpdateDTO;
use Modules\Auth\app\DTOs\ProfileUpdateDTO;
use Modules\Auth\app\Events\SendPasswordChangeEmailEvent;
use Modules\Auth\app\Exceptions\InvalidPasswordException;
use Modules\Auth\app\Exceptions\PasswordMustBeNewException;
use Modules\Auth\app\Exceptions\ProfileUpdateFailedException;
use Modules\Auth\app\Models\User;

class ProfileService
{
    public function __construct(private AuditService $auditService) {}

    public function profileUpdate(User $user, ProfileUpdateDTO $data): bool
    {
        try {
            return DB::transaction(function () use ($user, $data) {
                // 1. Fill the model without saving immediately
                $user->fill([
                    'first_name' => $data->firstName,
                    'last_name' => $data->lastName,
                    'phone' => $data->phone,
                    'timezone' => $data->timezone,
                ]);

                // 2. Capture each changed field with its old and new values
                $changes = [];
                foreach ($user->getDirty() as $key => $newValue) {
                    $changes[$key] = [
                        'old' => $user->getOriginal($key),
                        'new' => $newValue,
                    ];
                }

                // 3. Persist the changes
                $user->save();

                // 4. Log to Audit if any values actually changed
                if (! empty($changes)) {
                    $this->auditService->log(
                        action: 'user.profile.updated',
                        description: "Profile information was updated for {$user->email}",
                        organizer: null,
                        user: $user,
                        metadata: [
                            'user_id' => $user->id,
                            'user_uuid' => $user->uuid,
                            'changes' => $changes,
                        ]
                    );
                }

                return true;
            });
        } catch (\Exception $exception) {
            Log::error('Failed to update profile: '.$exception->getMessage());
            report($exception);
            throw new ProfileUpdateFailedException;
        }
    }

    public function passwordUpdate(User $user, PasswordUpdateDTO $passwordUpdateDTO)
    {

        if (! Hash::check($passwordUpdateDTO->currentPassword, $user->password)) {
            throw new InvalidPasswordException;
        }

        if ($passwordUpdateDTO->currentPassword === $passwordUpdateDTO->password) {
            throw new PasswordMustBeNewException;
        }

        try {

            DB::transaction(function () use ($user, $passwordUpdateDTO) {

                $user->update([
                    'password' => Hash::make($passwordUpdateDTO->password),
                    'password_changed_at' => now(),
                ]);

                $this->auditService->log(
                    action: 'user.password.updated',
                    description: "Password was updated for {$user->email}",
                    organizer: null,
                    user: $user,
                    metadata: [
                        'user_id' => $user->id,
                        'user_uuid' => $user->uuid,
                    ]
                );
            });

            $emailData = [
                'email' => $user->email,
                'first_name' => $user->first_name,
                'userName' => $user->first_name,
            ];

            event(new SendPasswordChangeEmailEvent($emailData));

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to update password: '.$exception->getMessage());
            report($exception);
            throw $exception;
        }
    }

    public function accountDelete(User $user, $data) {}
}
