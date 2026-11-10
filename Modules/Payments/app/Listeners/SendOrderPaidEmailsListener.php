<?php

declare(strict_types=1);

namespace Modules\Payments\app\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Payments\app\Events\OrderPaidEvent;
use Modules\Payments\app\Services\Mail\SendOrderConfirmationEmailService;
use Modules\Payments\app\Services\Mail\SendOrganizerSaleNotificationEmailService;
use Throwable;

class SendOrderPaidEmailsListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(
        private SendOrderConfirmationEmailService $orderConfirmationEmailService,
        private SendOrganizerSaleNotificationEmailService $organizerSaleNotificationEmailService,
    ) {}

    public function handle(OrderPaidEvent $event): void
    {
        // 1. Send confirmation & ticket access to buyer
        try {
            $this->orderConfirmationEmailService->execute($event->order, $event->payment);
        } catch (Throwable $e) {
            Log::error("Failed to send order confirmation email for order [{$event->order->id}]: {$e->getMessage()}");
        }

        // 2. Send sale notification to organizer
        try {
            $this->organizerSaleNotificationEmailService->execute($event->order, $event->payment);
        } catch (Throwable $e) {
            Log::error("Failed to send organizer sale notification for order [{$event->order->id}]: {$e->getMessage()}");
        }
    }
}
