<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Modules\Auth\app\Models\User;
use Modules\Events\app\DTOs\EventDTO;
use Modules\Events\app\DTOs\EventLocationDTO;
use Modules\Events\app\DTOs\EventScheduleDTO;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Events\EventCreatedEvent;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Services\EventService;
use Modules\Events\app\Services\Mail\SendEventCreatedEmailService;
use Modules\Notification\app\Services\MailerService;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('dispatches EventCreatedEvent when an event is successfully created', function () {
    EventFacade::fake([EventCreatedEvent::class]);

    $user = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $user->id,
        'name' => 'Tech Summit Organizers',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $eventDto = new EventDTO(
        title: 'Tech Summit 2026',
        description: 'An awesome conference for developers.',
        event_country: 'Nigeria',
        event_type: 'physical',
        event_format: 'Conference',
        event_category: ['technology'],
        visibility: 'public'
    );

    $scheduleDto = EventScheduleDTO::fromArray([
        'startDate' => '2026-12-01',
        'startTime' => '10:00',
        'endDate' => '2026-12-02',
        'endTime' => '18:00',
    ]);

    $locationDto = EventLocationDTO::fromArray([
        'venueName' => 'Landmark Centre',
        'venueAddress' => 'Water Corporation Drive, VI',
        'city' => 'Lagos',
    ]);

    $eventService = app(EventService::class);
    $event = $eventService->createEvent(
        organizer: $organizer,
        creator: $user,
        eventDto: $eventDto,
        scheduleDto: $scheduleDto,
        eventLocationDto: $locationDto
    );

    expect($event)->toBeInstanceOf(Event::class);

    EventFacade::assertDispatched(EventCreatedEvent::class, function ($e) use ($event, $organizer, $user) {
        return $e->event->id === $event->id
            && $e->organizer->id === $organizer->id
            && $e->creator->id === $user->id;
    });
});

it('sends event creation email via mailer service', function () {
    $user = User::factory()->create(['email' => 'creator@example.com', 'first_name' => 'Alex', 'account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $user->id,
        'name' => 'Acme Events',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $user->id,
        'title' => 'Design & Code 2026',
        'description' => 'Conference description',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Conference',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $mailerService = Mockery::mock(MailerService::class);
    $mailerService->shouldReceive('send')
        ->once()
        ->with(
            'creator@example.com',
            'event_created',
            Mockery::on(function ($payload) {
                return $payload['eventTitle'] === 'Design & Code 2026'
                    && $payload['organizerName'] === 'Acme Events'
                    && $payload['creatorName'] === 'Alex';
            })
        );

    $service = new SendEventCreatedEmailService($mailerService);
    $service->execute($event, $organizer, $user);
});
