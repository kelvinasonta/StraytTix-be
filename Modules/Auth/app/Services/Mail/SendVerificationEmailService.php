<?php

namespace Modules\Auth\app\Services\Mail;

use Modules\Notification\app\Services\MailerService;

class SendVerificationEmailService
{
    public function __construct(
        private MailerService $mailerService,
    ) {}

    public function execute(array $userData)
    {
        $this->mailerService->send(
            $userData['email'],
            'user_verification',
            [
                'name' => $userData['firstName'],
                'siteName' => 'TestMan LTD',
                'verification_link' => config('app.frontend_url').'/verify-email?token='.($userData['token'] ?? ''),
                'expiry_time' => isset($userData['expires_at']) ? $userData['expires_at']->diffForHumans() : '30 minutes',
            ]
        );
    }
}
