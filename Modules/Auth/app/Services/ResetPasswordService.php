<?php

namespace Modules\Auth\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Actions\ResetPasswordAction;
use Modules\Auth\app\DTOs\ResetPasswordDTO;
use Modules\Auth\app\Exceptions\AuthException;
use Modules\Auth\app\Exceptions\ExpiredResetTokenException;
use Modules\Auth\app\Exceptions\ResetPasswordFailedException;
use Modules\Auth\app\Models\PasswordResetToken;

class ResetPasswordService
{
    public function __construct(
        private ResetPasswordAction $resetPasswordAction,
    ) {}

    public function execute(ResetPasswordDTO $dto, string $ip, string $userAgent): bool
    {
        try {
            DB::transaction(function () use ($dto) {
                $this->resetPasswordAction->handle($dto);
            });

            return true;
        } catch (ExpiredResetTokenException $e) {
            $hashedToken = hash('sha256', $dto->token);
            PasswordResetToken::where('token', $hashedToken)->delete();

            throw $e;
        } catch (AuthException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Reset password failed: '.$e->getMessage());

            throw new ResetPasswordFailedException;
        }
    }
}
