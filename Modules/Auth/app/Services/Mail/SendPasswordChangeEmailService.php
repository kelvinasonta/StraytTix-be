<?php

declare(strict_types=1);

namespace Modules\Auth\app\Services\Mail;

use Modules\Notification\app\Services\MailerService;

class SendPasswordChangeEmailService
{
    public function __construct(
        private MailerService $mailerService,
    ) {}

    public function execute(array $emailData): void
    {
        $name = $emailData['userName'] ?? $emailData['name'] ?? $emailData['first_name'] ?? 'User';
        $siteName = config('app.name', 'StraytTix');

        $this->mailerService->send(
            $emailData['email'],
            'password_changed',
            [
                'userName' => $name,
                'name' => $name,
                'siteName' => $siteName,
            ]
        );
    }
}
