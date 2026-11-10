<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Payments\app\Services\OrderService;
use Modules\Tickets\app\Models\Ticket;
use Modules\Tickets\app\Services\Mail\SendEventReminderEmailService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Release expired ticket reservations back to available stock.
 */
Artisan::command('orders:cancel-expired', function (OrderService $orderService) {
    $count = $orderService->cancelExpiredReservations();
    $this->info("Cancelled {$count} expired ticket reservation(s).");
})->purpose('Cancel pending orders that have exceeded their reservation window and release held tickets');

// Schedule expired reservations cleanup every minute
Schedule::command('orders:cancel-expired')->everyMinute()->withoutOverlapping();

/**
 * Send 24-hour upcoming event reminders to ticket holders.
 */
Artisan::command('events:send-reminders', function (SendEventReminderEmailService $reminderService) {
    // Look for events starting between 23 and 25 hours from now
    $windowStart = now()->addHours(23);
    $windowEnd = now()->addHours(25);

    $tickets = Ticket::with(['event.schedule', 'event.location', 'order'])
        ->where('status', 'valid')
        ->whereNull('checked_in_at')
        ->whereHas('event.schedule', function ($query) use ($windowStart, $windowEnd) {
            $query->whereBetween('start_date', [$windowStart, $windowEnd]);
        })
        ->get();

    $count = 0;
    foreach ($tickets as $ticket) {
        if (! empty($ticket->attendee_email)) {
            $reminderService->execute($ticket);
            $count++;
        }
    }

    $this->info("Sent {$count} event reminder email(s).");
})->purpose('Send reminder emails with ticket QR code access to attendees 24 hours before their event');

// Schedule event reminders to run hourly
Schedule::command('events:send-reminders')->hourly()->withoutOverlapping();
