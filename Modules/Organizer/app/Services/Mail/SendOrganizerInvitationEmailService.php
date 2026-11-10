<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Services\Mail;

use Modules\Notification\app\Services\MailerService;

class SendOrganizerInvitationEmailService
{
    public function __construct(
        private MailerService $mailerService,
    ) {}

    public function execute(array $emailData): void
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $isResend = (bool) ($emailData['is_resend'] ?? false);
        $templateSlug = $isResend ? 'resend_invitation_link' : 'send_invitation_link';

        $roleName = ! empty($emailData['role'])
            ? ucfirst(str_replace('_', ' ', (string) $emailData['role']))
            : 'Team Member';

        $payload = [
            'organizerName' => $emailData['organizer_name'] ?? 'Your Organization',
            'role' => $roleName,
            'siteName' => config('app.name', 'StaytTix'),
            'invitation_link' => "{$frontendUrl}/invitations/accept?token=".($emailData['invitation_token'] ?? ''),
            'expiry_time' => isset($emailData['expires_at']) && method_exists($emailData['expires_at'], 'diffForHumans')
                ? $emailData['expires_at']->diffForHumans()
                : '7 days',
        ];

        try {
            $this->mailerService->send($emailData['email'], $templateSlug, $payload);
        } catch (\Throwable) {
            $this->mailerService->send($emailData['email'], 'event_staff_invitation', $payload);
        }
    }
}
