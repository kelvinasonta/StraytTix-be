<?php

namespace Modules\Auth\app\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Auth\app\Events\ForgotPasswordEvent;
use Modules\Auth\app\Services\Mail\SendForgotPasswordEmailService;

class ForgotPasswordListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private SendForgotPasswordEmailService $sendForgotPasswordEmailService,
    ) {}

    public function handle(ForgotPasswordEvent $event): void
    {
        $this->sendForgotPasswordEmailService->execute($event->emailData);
    }
}
