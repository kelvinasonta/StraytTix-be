<?php

namespace Modules\Auth\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Actions\ForgotPasswordAction;
use Modules\Auth\app\Events\ForgotPasswordEvent;
use Modules\Auth\app\Exceptions\AuthException;
use Modules\Auth\app\Exceptions\ForgotPasswordFailedException;

class ForgotPasswordService
{
    public function __construct(
        private ForgotPasswordAction $forgotPasswordAction,
        private AuditService $auditService,
    ) {}

    /**
     * Handle forgot password request.
     */
    public function execute(string $email, string $ip, string $userAgent): bool
    {
        try {
            $result = DB::transaction(function () use ($email, $ip, $userAgent) {
                return $this->forgotPasswordAction->handle($email, $ip, $userAgent);
            });

            if ($result !== null) {
                $user = $result['user'];

                $emailData = [
                    'email' => $user->email,
                    'firstName' => $user->first_name,
                    'token' => $result['token'],
                    'expires_at' => $result['expires_at'],
                ];

                event(new ForgotPasswordEvent($emailData));

                $this->auditService->log(
                    action: 'user.password_reset_requested',
                    description: "Password reset request initiated for {$user->email}",
                    user: $user,
                    metadata: [
                        'ip' => $ip,
                        'user_agent' => $userAgent,
                    ]
                );
            }

            return true;
        } catch (AuthException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Forgot password failed: '.$e->getMessage());

            throw new ForgotPasswordFailedException;
        }
    }
}
