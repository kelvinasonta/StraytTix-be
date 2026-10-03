<?php

namespace Modules\Auth\app\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Auth\app\Events\LoginAttemptEvent;
use Modules\Auth\app\Services\LogLoginAttemptService;

class LoginAttemptListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private LogLoginAttemptService $logger) {}

    public function handle(LoginAttemptEvent $event): void
    {
        $this->logger->execute(
            $event->user,
            $event->email,
            $event->status,
            $event->ipAddress,
            $event->userAgent
        );
    }
}
