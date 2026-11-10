<?php

namespace Modules\Auth\app\Services\Mail;

use Modules\Notification\app\Services\MailerService;

class SendWelcomeEmailService
{
    public function __construct(
        private MailerService $mailerService,
    ) {}

    public function execute(array $userData)
    {
        $this->mailerService->send(
            $userData['email'],
            'welcome',
            [
                'name' => $userData['firstName'],
                'siteName' => config('app.name', 'StraytTix'),
            ]
        );
    }
}
