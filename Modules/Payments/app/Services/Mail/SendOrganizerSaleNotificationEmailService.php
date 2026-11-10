<?php

declare(strict_types=1);

namespace Modules\Payments\app\Services\Mail;

use Modules\Notification\app\Services\MailerService;
use Modules\Payments\app\Models\Order;
use Modules\Payments\app\Models\Payment;

class SendOrganizerSaleNotificationEmailService
{
    public function __construct(
        private MailerService $mailerService
    ) {}

    /**
     * Notify organizer of a new ticket sale.
     */
    public function execute(Order $order, Payment $payment): void
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        $order->loadMissing(['event', 'organizer.owner', 'tickets']);

        $organizer = $order->organizer;
        $organizerEmail = $organizer?->email ?? $organizer?->owner?->email;

        if (empty($organizerEmail)) {
            return;
        }

        $event = $order->event;
        $ticketCount = $order->tickets->count();

        $payload = [
            'name' => $organizer->name ?? 'Organizer',
            'organizerName' => $organizer->name ?? 'Organizer',
            'eventTitle' => $event?->title ?? 'Your Event',
            'orderNumber' => $order->order_number,
            'buyerName' => $order->customer_name ?? 'Anonymous Buyer',
            'buyerEmail' => $order->customer_email,
            'ticketCount' => (string) $ticketCount,
            'orderTotal' => strtoupper((string) $order->currency).' '.number_format((float) $order->total, 2),
            'viewOrderUrl' => "{$frontendUrl}/organizers/{$organizer->uuid}/events/{$event?->uuid}/orders",
            'siteName' => config('app.name', 'EventsOS'),
        ];

        $this->mailerService->send($organizerEmail, 'organizer_ticket_sale', $payload);
    }
}
