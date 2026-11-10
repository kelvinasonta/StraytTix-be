<?php

declare(strict_types=1);

namespace Modules\Events\app\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Events\app\Events\EventCreatedEvent;
use Modules\Events\app\Services\Mail\SendEventCreatedEmailService;
use Throwable;

class SendEventCreatedEmailListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Number of times the queued job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        private SendEventCreatedEmailService $sendEventCreatedEmailService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(EventCreatedEvent $event): void
    {
        try {
            $this->sendEventCreatedEmailService->execute(
                $event->event,
                $event->organizer,
                $event->creator
            );
        } catch (Throwable $e) {
            Log::error("Failed to send event created email for event [{$event->event->id}]: {$e->getMessage()}");
            throw $e;
        }
    }
}
