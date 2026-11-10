<?php

declare(strict_types=1);

namespace Modules\Payments\app\Services\Mail;

use Illuminate\Support\Carbon;
use Modules\Notification\app\Services\MailerService;
use Modules\Payments\app\Models\Order;
use Modules\Payments\app\Models\Payment;

class SendOrderConfirmationEmailService
{
    public function __construct(
        private MailerService $mailerService
    ) {}

    /**
     * Send order confirmation and tickets to the buyer/attendee.
     */
    public function execute(Order $order, Payment $payment): void
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        $order->loadMissing(['event.location', 'event.schedule', 'tickets.ticketType']);

        $event = $order->event;
        $schedule = $event?->schedule;
        $location = $event?->location;

        $startDate = 'TBA';
        if (! empty($schedule?->start_date)) {
            try {
                $time = ! empty($schedule->start_time) ? ' '.$schedule->start_time : '';
                $startDate = Carbon::parse($schedule->start_date.$time)->format('F j, Y g:i A');
            } catch (\Throwable) {
                $startDate = (string) $schedule->start_date;
            }
        }
        $locationName = $location?->name ?? $location?->address ?? ($event?->format?->value === 'online' ? 'Online Event' : 'TBA');

        // Build list of tickets summary for the email
        $ticketsListHtml = '';
        foreach ($order->tickets as $ticket) {
            $ticketTypeName = $ticket->ticketType?->name ?? 'General Admission';
            $ticketsListHtml .= "
                <div style='margin-bottom: 12px; padding: 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px;'>
                    <div style='font-weight: 600; color: #0f172a;'>🎫 {$ticketTypeName} - #{$ticket->ticket_number}</div>
                    <div style='font-size: 13px; color: #64748b;'>Attendee: {$ticket->attendee_name} ({$ticket->attendee_email})</div>
                    <div style='font-size: 13px; color: #059669; font-weight: 500;'>Status: Valid</div>
                </div>";
        }

        $payload = [
            'name' => $order->customer_name ?? 'Valued Attendee',
            'customerName' => $order->customer_name ?? 'Valued Attendee',
            'orderNumber' => $order->order_number,
            'eventTitle' => $event?->title ?? 'Your Event',
            'eventStartDate' => $startDate,
            'eventLocation' => $locationName,
            'ticketCount' => (string) $order->tickets->count(),
            'ticketsListHtml' => $ticketsListHtml,
            'orderTotal' => strtoupper((string) $order->currency).' '.number_format((float) $order->total, 2),
            'viewOrderUrl' => "{$frontendUrl}/orders/{$order->uuid}",
            'downloadTicketsUrl' => "{$frontendUrl}/orders/{$order->uuid}/tickets",
            'siteName' => config('app.name', 'EventsOS'),
        ];

        $this->mailerService->send($order->customer_email, 'order_confirmation', $payload);
    }
}
