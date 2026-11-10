<?php

namespace Modules\Organizer\app\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Organizer\app\Events\OrganizerInvitationCreatedEvent;
use Modules\Organizer\app\Events\OrganizerVerificationReviewedEvent;
use Modules\Organizer\app\Listeners\OrganizerInvitationCreatedListener;
use Modules\Organizer\app\Listeners\SendOrganizerVerificationEmailListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        OrganizerInvitationCreatedEvent::class => [
            OrganizerInvitationCreatedListener::class,
        ],
        OrganizerVerificationReviewedEvent::class => [
            SendOrganizerVerificationEmailListener::class,
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
