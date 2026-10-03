<?php

namespace Modules\Auth\app\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Auth\app\Events\EmailVerifiedEvent;
use Modules\Auth\app\Services\Mail\SendWelcomeEmailService;

class SendWelcomeEmailListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        private SendWelcomeEmailService $sendWelcomeEmailService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(EmailVerifiedEvent $event): void
    {
        $this->sendWelcomeEmailService->execute($event->userData);
    }
}
