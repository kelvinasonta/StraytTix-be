<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Services\Mail;

use Modules\Notification\app\Services\MailerService;
use Modules\Organizer\app\Models\OrganizerVerification;

class SendOrganizerVerificationEmailService
{
    public function __construct(
        private MailerService $mailerService
    ) {}

    /**
     * Send verification review outcome (approval or rejection) to the organizer.
     */
    public function execute(OrganizerVerification $verification, string $status, ?string $reason = null): void
    {
        $verification->loadMissing(['organizer.owner', 'requester']);

        $organizer = $verification->organizer;
        $recipientEmail = $organizer?->email ?? $organizer?->owner?->email ?? $verification->requester?->email;

        if (empty($recipientEmail)) {
            return;
        }

        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $isApproved = ($status === 'verified');

        $payload = [
            'name' => $organizer?->name ?? $verification->business_name,
            'organizerName' => $organizer?->name ?? $verification->business_name,
            'businessName' => $verification->business_name,
            'reason' => $reason ?? 'Documents did not meet platform verification guidelines.',
            'dashboardUrl' => "{$frontendUrl}/organizers/{$organizer?->uuid}",
            'resubmitUrl' => "{$frontendUrl}/organizers/{$organizer?->uuid}/settings/verification",
            'siteName' => config('app.name', 'EventsOS'),
        ];

        $templateSlug = $isApproved ? 'organizer_verification_approved' : 'organizer_verification_rejected';

        $this->mailerService->send($recipientEmail, $templateSlug, $payload);
    }
}
