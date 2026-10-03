<?php

namespace Modules\Auth\app\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Auth\app\Events\UserRegisteredEvent;
use Modules\Auth\app\Services\Mail\SendVerificationEmailService;

class SendVerificationEmailListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        private SendVerificationEmailService $sendVerificationEmailService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(UserRegisteredEvent $event): void
    {
        $this->sendVerificationEmailService->execute($event->userData);
    }
}
