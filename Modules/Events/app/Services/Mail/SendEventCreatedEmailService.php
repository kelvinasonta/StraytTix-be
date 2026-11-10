<?php

declare(strict_types=1);

namespace Modules\Events\app\Services\Mail;

use Illuminate\Support\Carbon;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Notification\app\Services\MailerService;
use Modules\Organizer\app\Models\Organizer;

class SendEventCreatedEmailService
{
    public function __construct(
        private MailerService $mailerService
    ) {}

    /**
     * Send email notification to creator / organizer on successful event creation.
     */
    public function execute(Event $event, Organizer $organizer, User $creator): void
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        $schedule = $event->relationLoaded('schedule') ? $event->schedule : $event->schedule()->first();
        $location = $event->relationLoaded('location') ? $event->location : $event->location()->first();

        $startDate = 'TBA';
        if (! empty($schedule?->start_date)) {
            try {
                $time = ! empty($schedule->start_time) ? ' '.$schedule->start_time : '';
                $startDate = Carbon::parse($schedule->start_date.$time)->format('F j, Y g:i A');
            } catch (\Throwable) {
                $startDate = (string) $schedule->start_date;
            }
        }
        $locationName = $location?->name ?? $location?->address ?? ($event->format?->value === 'online' ? 'Online Event' : 'TBA');

        $payload = [
            'name' => $creator->first_name ?? $creator->name ?? 'Organizer',
            'creatorName' => $creator->first_name ?? $creator->name ?? 'Organizer',
            'eventTitle' => $event->title,
            'organizerName' => $organizer->name,
            'eventStartDate' => $startDate,
            'eventLocation' => $locationName,
            'eventStatus' => ucfirst((string) ($event->status->value ?? $event->status)),
            'eventManageLink' => "{$frontendUrl}/organizers/{$organizer->uuid}/events/{$event->uuid}",
            'eventPublicLink' => "{$frontendUrl}/events/{$event->slug}",
            'siteName' => config('app.name', 'EventsOS'),
        ];

        $this->mailerService->send($creator->email, 'event_created', $payload);
    }
}
