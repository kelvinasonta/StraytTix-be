<?php

namespace Modules\Auth\app\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Auth\app\Events\SendPasswordChangeEmailEvent;
use Modules\Auth\app\Services\Mail\SendPasswordChangeEmailService;

class SendPasswordChangeEmailListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private SendPasswordChangeEmailService $sendPasswordChangeEmailService,
    ) {}

    public function handle(SendPasswordChangeEmailEvent $event): void
    {
        $this->sendPasswordChangeEmailService->execute($event->emailData);
    }
}
