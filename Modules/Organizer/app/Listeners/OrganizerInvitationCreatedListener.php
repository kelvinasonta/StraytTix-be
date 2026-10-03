<?php

namespace Modules\Organizer\app\Listeners;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Organizer\app\Events\OrganizerInvitationCreatedEvent;
use Modules\Organizer\app\Services\Mail\SendOrganizerInvitationEmailService;

class OrganizerInvitationCreatedListener implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function __construct(
        private SendOrganizerInvitationEmailService $sendOrganizerInvitationEmailService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrganizerInvitationCreatedEvent $event): void
    {
        $this->sendOrganizerInvitationEmailService->execute($event->data);
    }
}
