<?php

namespace Modules\Auth\app\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Auth\app\Events\EmailVerifiedEvent;
use Modules\Auth\app\Events\ForgotPasswordEvent;
use Modules\Auth\app\Events\LoginAttemptEvent;
use Modules\Auth\app\Events\ResendEmailVerificationEvent;
use Modules\Auth\app\Events\SendPasswordChangeEmailEvent;
use Modules\Auth\app\Events\UserRegisteredEvent;
use Modules\Auth\app\Listeners\ForgotPasswordListener;
use Modules\Auth\app\Listeners\LoginAttemptListener;
use Modules\Auth\app\Listeners\ResendEmailVerificationListener;
use Modules\Auth\app\Listeners\SendPasswordChangeEmailListener;
use Modules\Auth\app\Listeners\SendVerificationEmailListener;
use Modules\Auth\app\Listeners\SendWelcomeEmailListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        LoginAttemptEvent::class => [
            LoginAttemptListener::class,
        ],
        UserRegisteredEvent::class => [
            SendVerificationEmailListener::class,
        ],
        EmailVerifiedEvent::class => [
            SendWelcomeEmailListener::class,
        ],
        ResendEmailVerificationEvent::class => [
            ResendEmailVerificationListener::class,
        ],
        ForgotPasswordEvent::class => [
            ForgotPasswordListener::class,
        ],
        SendPasswordChangeEmailEvent::class => [
            SendPasswordChangeEmailListener::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
