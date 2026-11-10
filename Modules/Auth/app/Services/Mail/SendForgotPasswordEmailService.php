<?php

namespace Modules\Auth\app\Services\Mail;

use Modules\Notification\app\Services\MailerService;

class SendForgotPasswordEmailService
{
    public function __construct(
        private MailerService $mailerService,
    ) {}

    public function execute(array $emailData): void
    {
        $this->mailerService->send(
            $emailData['email'],
            'forgot_password',
            [
                'name' => $emailData['firstName'],
                'siteName' => config('app.name', 'StraytTix'),
                'reset_link' => config('app.frontend_url').'/reset-password?token='.($emailData['token'] ?? ''),
                'expiry_time' => isset($emailData['expires_at']) ? $emailData['expires_at']->diffForHumans() : '30 minutes',
            ]
        );
    }
}
