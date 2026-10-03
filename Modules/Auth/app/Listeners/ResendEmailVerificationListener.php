<?php

namespace Modules\Auth\app\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Auth\app\Events\ResendEmailVerificationEvent;
use Modules\Auth\app\Services\Mail\ResendVerificationEmailService;

class ResendEmailVerificationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        private ResendVerificationEmailService $resendVerificationEmailService,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(ResendEmailVerificationEvent $event): void
    {
        $this->resendVerificationEmailService->execute($event->emailData);
    }
}
