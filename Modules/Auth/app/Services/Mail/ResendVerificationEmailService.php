<?php

namespace Modules\Auth\app\Services\Mail;

use Modules\Notification\app\Services\MailerService;

class ResendVerificationEmailService
{
    public function __construct(
        private MailerService $mailerService,
    ) {}

    public function execute(array $emailData)
    {
        $this->mailerService->send(
            $emailData['email'],
            'resend_verification_email',
            [
                'name' => $emailData['firstName'],
                'siteName' => config('app.name', 'StraytTix'),
                'verification_link' => config('app.frontend_url').'/verify-email?token='.($emailData['token'] ?? ''),
                'expiry_time' => isset($emailData['expires_at']) ? $emailData['expires_at']->diffForHumans() : '30 minutes',
            ]
        );
    }
}
