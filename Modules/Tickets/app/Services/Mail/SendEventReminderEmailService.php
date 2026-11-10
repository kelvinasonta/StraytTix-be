<?php

declare(strict_types=1);

namespace Modules\Tickets\app\Services\Mail;

use Illuminate\Support\Carbon;
use Modules\Notification\app\Services\MailerService;
use Modules\Tickets\app\Models\Ticket;

class SendEventReminderEmailService
{
    public function __construct(
        private MailerService $mailerService
    ) {}

    /**
     * Send 24-hour event reminder with ticket QR code to attendee.
     */
    public function execute(Ticket $ticket): void
    {
        $ticket->loadMissing(['event.location', 'event.schedule', 'order']);

        $event = $ticket->event;
        $schedule = $event?->schedule;
        $location = $event?->location;

        $startDate = 'Tomorrow';
        if (! empty($schedule?->start_date)) {
            try {
                $time = ! empty($schedule->start_time) ? ' '.$schedule->start_time : '';
                $startDate = Carbon::parse($schedule->start_date.$time)->format('l, F j, Y \a\t g:i A');
            } catch (\Throwable) {
                $startDate = (string) $schedule->start_date;
            }
        }
        $locationName = $location?->name ?? $location?->address ?? ($event?->format?->value === 'online' ? 'Online Event' : 'TBA');

        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        $payload = [
            'name' => $ticket->attendee_name ?? 'Attendee',
            'attendeeName' => $ticket->attendee_name ?? 'Attendee',
            'eventTitle' => $event?->title ?? 'Your Event',
            'eventStartDate' => $startDate,
            'eventLocation' => $locationName,
            'ticketNumber' => $ticket->ticket_number,
            'ticketUuid' => $ticket->uuid,
            'viewTicketUrl' => "{$frontendUrl}/orders/{$ticket->order?->uuid}/tickets",
            'siteName' => config('app.name', 'EventsOS'),
        ];

        $this->mailerService->send($ticket->attendee_email, 'event_reminder', $payload);
    }
}
