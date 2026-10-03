<?php

namespace Modules\Organizer\app\Services\Mail;

use Modules\Notification\app\Services\MailerService;

class SendOrganizerInvitationEmailService
{
    public function __construct(
        private MailerService $mailerService,
    ) {}

    public function execute(array $emailData)
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        $this->mailerService->send(
            $emailData['email'],
            'event_staff_invitation',
            [
                'organizerName' => $emailData['organizer_name'],
                'siteName' => config('app.name', 'EventsOS'),
                'invitation_link' => "{$frontendUrl}/invitations/accept?token=".($emailData['invitation_token'] ?? ''),
                'expiry_time' => isset($emailData['expires_at']) ? $emailData['expires_at']->diffForHumans() : '7 days',
            ]
        );
    }
}
