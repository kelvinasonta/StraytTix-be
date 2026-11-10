<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Organizer\app\Events\OrganizerVerificationReviewedEvent;
use Modules\Organizer\app\Services\Mail\SendOrganizerVerificationEmailService;
use Throwable;

class SendOrganizerVerificationEmailListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private SendOrganizerVerificationEmailService $sendOrganizerVerificationEmailService
    ) {}

    public function handle(OrganizerVerificationReviewedEvent $event): void
    {
        try {
            $this->sendOrganizerVerificationEmailService->execute(
                $event->verification,
                $event->status,
                $event->reason
            );
        } catch (Throwable $e) {
            Log::error("Failed to send organizer verification email for verification [{$event->verification->id}]: {$e->getMessage()}");
            throw $e;
        }
    }
}
