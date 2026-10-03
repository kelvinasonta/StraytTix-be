<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('owner can create an event with complete location, recurring schedule, lineup, and agenda', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Tech Summit Hub',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $payload = [
        'title' => 'Global Developer Summit 2026',
        'description' => 'A comprehensive 3-day deep dive into modern systems.',
        'eventCountry' => 'Nigeria',
        'eventType' => EventType::PHYSICAL->value,
        'eventFormat' => 'Conference',
        'eventCategory' => ['Tech', 'Design', 'AI'],
        'venueName' => 'Eko Convention Centre',
        'venueAddress' => 'Plot 1415 Adetokunbo Ademola St',
        'streetAddress' => 'Victoria Island',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'zip' => '101241',
        'landmark' => 'Near Eko Hotel',
        'longitude' => 3.4244,
        'latitude' => 6.4281,
        'scheduleType' => 'recurring',
        'scheduleTimezone' => 'Africa/Lagos',
        'eventFrequency' => 'weekly',
        'startDate' => now()->addDays(5)->toDateString(),
        'startTime' => '10:00',
        'endDate' => now()->addDays(5)->toDateString(),
        'endTime' => '18:00',
        'recurringEndType' => 'after_occurrences',
        'recurringOccurrences' => 4,
        'schedules' => [
            [
                'day' => 'Day 1',
                'title' => 'Keynote Address & Opening Remarks',
                'description' => 'Opening ceremony and industry keynote.',
                'startTime' => '10:00',
                'endTime' => '12:00',
            ],
        ],
        'lineup' => [
            [
                'name' => 'Sarah Johnson',
                'role' => 'Principal Engineer',
                'bio' => 'Expert in distributed architecture.',
                'avatarUrl' => 'https://example.com/avatar.jpg',
                'links' => [
                    ['platform' => 'github', 'url' => 'https://github.com/sarah'],
                ],
            ],
        ],
        'status' => EventStatus::DRAFT->value,
        'visibility' => EventVisibility::PUBLIC->value,
        'bannerUrl' => 'https://example.com/banner.jpg',
    ];

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/events", $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Global Developer Summit 2026')
        ->assertJsonPath('data.eventCountry', 'Nigeria')
        ->assertJsonPath('data.location.venueName', 'Eko Convention Centre')
        ->assertJsonPath('data.location.city', 'Lagos')
        ->assertJsonPath('data.schedule.type', 'recurring')
        ->assertJsonPath('data.schedule.recurringOccurrences', 4)
        ->assertJsonPath('data.lineup.0.name', 'Sarah Johnson')
        ->assertJsonPath('data.schedules.0.title', 'Keynote Address & Opening Remarks');

    $this->assertDatabaseHas('events', [
        'organizer_id' => $organizer->id,
        'title' => 'Global Developer Summit 2026',
    ]);

    $this->assertDatabaseHas('event_schedules', [
        'recurring_occurrences' => 4,
    ]);

    $this->assertDatabaseHas('events_location', [
        'venue_name' => 'Eko Convention Centre',
        'city' => 'Lagos',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'organizer_id' => $organizer->id,
        'action' => 'event.created',
    ]);
});

test('staff cannot create or delete an event', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $staff = User::factory()->create(['account_type' => 'organizer']);

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Festivals Inc',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staff->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Summer Fest',
        'status' => EventStatus::DRAFT,
    ]);

    // Staff cannot create
    $this->actingAs($staff, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/events", [
            'title' => 'Unauthorized Event',
        ])
        ->assertStatus(403);

    // Staff cannot delete
    $this->actingAs($staff, 'sanctum')
        ->deleteJson("/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}")
        ->assertStatus(403);
});

test('organizer member can list and view their organizer events', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Live Shows',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Concert A',
        'status' => EventStatus::PUBLISHED,
    ]);

    Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Concert B',
        'status' => EventStatus::DRAFT,
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/organizers/{$organizer->uuid}/events");

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data.data');
});

test('manager can publish and cancel events', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $manager = User::factory()->create(['account_type' => 'organizer']);

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Workshops HQ',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $manager->id,
        'role' => OrganizerMemberRole::MANAGER,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Coding Bootcamp',
        'status' => EventStatus::DRAFT,
    ]);

    $event->ticketTypes()->create([
        'name' => 'Standard Ticket',
        'type' => 'free',
        'quantity' => 50,
        'currency' => 'NGN',
    ]);

    // Publish
    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/publish")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'published');

    // Cancel
    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/cancel")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'cancelled');
});

test('public explore endpoint returns published public events', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Open Community',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    // Published & Public & Upcoming
    $publicEvent = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Public Showcase',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);
    $publicEvent->schedule()->create([
        'schedule_type' => 'single',
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'start_time' => '09:00',
    ]);

    // Draft (should not be in explore)
    Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Draft Showcase',
        'status' => EventStatus::DRAFT,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    // Private (should not be in explore)
    Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Private Meeting',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PRIVATE,
    ]);

    $response = $this->getJson('/api/v1/explore/events');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.title', 'Public Showcase');
});

test('manager can unpublish a published event back to draft', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $manager = User::factory()->create(['account_type' => 'organizer']);

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Tech Talks Hub',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $manager->id,
        'role' => OrganizerMemberRole::MANAGER,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Advanced AI Summit',
        'status' => EventStatus::PUBLISHED,
    ]);

    $event->ticketTypes()->create([
        'name' => 'General Admission',
        'type' => 'paid',
        'price' => 15000,
        'quantity' => 100,
        'currency' => 'NGN',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/unpublish");

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'draft');

    $this->assertDatabaseHas('events', [
        'id' => $event->id,
        'status' => 'draft',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'organizer_id' => $organizer->id,
        'action' => 'event.unpublished',
    ]);
});

test('manager can duplicate an event with all relations and ticket tiers', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $manager = User::factory()->create(['account_type' => 'organizer']);

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'ScaleOps Global',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $manager->id,
        'role' => OrganizerMemberRole::MANAGER,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Cloud Architecture Masterclass',
        'description' => 'Hands on AWS and GCP architecture.',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Workshop',
        'event_category' => ['Cloud', 'DevOps'],
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
        'banner_url' => 'https://example.com/banner.png',
        'meta' => [
            'faqs' => [
                ['question' => 'Is lunch provided?', 'answer' => 'Yes, lunch is included.'],
            ],
            'galleryUrls' => ['https://example.com/photo1.jpg'],
        ],
    ]);

    $event->location()->create([
        'venue_name' => 'Civic Centre',
        'city' => 'Lagos',
        'state' => 'Lagos',
    ]);

    $event->schedule()->create([
        'schedule_type' => 'single',
        'start_date' => now()->addDays(10)->toDateString(),
        'start_time' => '09:00',
        'end_date' => now()->addDays(10)->toDateString(),
        'end_time' => '17:00',
    ]);

    $event->scheduleItems()->create([
        'title' => 'Registration & Welcome',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'schedule_date' => now()->addDays(10)->toDateString(),
    ]);

    $event->lineup()->create([
        'name' => 'John Doe',
        'role' => 'Cloud Architect',
    ]);

    $event->ticketTypes()->create([
        'name' => 'VIP Pass',
        'type' => 'paid',
        'price' => 50000,
        'quantity' => 20,
        'sold_quantity' => 15,
        'reserved_quantity' => 2,
        'currency' => 'NGN',
    ]);

    $response = $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/duplicate");

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Cloud Architecture Masterclass (Copy)')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.location.venueName', 'Civic Centre')
        ->assertJsonPath('data.location.city', 'Lagos')
        ->assertJsonPath('data.schedules.0.title', 'Registration & Welcome')
        ->assertJsonPath('data.lineup.0.name', 'John Doe')
        ->assertJsonPath('data.ticketTypes.0.name', 'VIP Pass')
        ->assertJsonPath('data.ticketTypes.0.soldQuantity', 0)
        ->assertJsonPath('data.ticketTypes.0.reservedQuantity', 0);

    $this->assertDatabaseHas('events', [
        'organizer_id' => $organizer->id,
        'title' => 'Cloud Architecture Masterclass (Copy)',
        'status' => 'draft',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'organizer_id' => $organizer->id,
        'action' => 'event.duplicated',
    ]);
});

test('organizer member can fetch event statistics and metrics', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Live Hub Events',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Fintech Innovators Night',
        'status' => EventStatus::PUBLISHED,
    ]);

    $ticketType1 = $event->ticketTypes()->create([
        'name' => 'Early Bird',
        'type' => 'paid',
        'price' => 10000,
        'quantity' => 50,
        'sold_quantity' => 30,
        'reserved_quantity' => 5,
        'currency' => 'NGN',
    ]);

    $ticketType2 = $event->ticketTypes()->create([
        'name' => 'Standard',
        'type' => 'paid',
        'price' => 20000,
        'quantity' => 100,
        'sold_quantity' => 20,
        'reserved_quantity' => 0,
        'currency' => 'NGN',
    ]);

    // Add tickets with check-in
    $event->tickets()->create([
        'user_id' => $owner->id,
        'ticket_type_id' => $ticketType1->id,
        'price_paid' => 10000,
        'currency' => 'NGN',
        'status' => 'valid',
        'checked_in_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/stats");

    // Total revenue = (30 * 10000) + (20 * 20000) = 300,000 + 400,000 = 700,000
    $response->assertStatus(200)
        ->assertJsonPath('data.totalCapacity', 150)
        ->assertJsonPath('data.soldTickets', 50)
        ->assertJsonPath('data.reservedTickets', 5)
        ->assertJsonPath('data.remainingTickets', 95)
        ->assertJsonPath('data.totalRevenue', 700000)
        ->assertJsonPath('data.checkedInCount', 1)
        ->assertJsonPath('data.currency', 'NGN')
        ->assertJsonCount(2, 'data.ticketBreakdown');
});

test('event can be created and updated with FAQs and gallery URLs', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Creative Hub',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $payload = [
        'title' => 'Design & UX Festival 2026',
        'description' => 'Celebrating world-class design.',
        'eventCountry' => 'Nigeria',
        'eventType' => EventType::PHYSICAL->value,
        'eventFormat' => 'Festival',
        'venueName' => 'Landmark Centre',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'scheduleType' => 'single',
        'scheduleTimezone' => 'Africa/Lagos',
        'startDate' => now()->addDays(20)->toDateString(),
        'startTime' => '09:00',
        'endDate' => now()->addDays(20)->toDateString(),
        'endTime' => '18:00',
        'faqs' => [
            ['question' => 'Is parking free?', 'answer' => 'Yes, underground parking is available.'],
            ['question' => 'Will sessions be recorded?', 'answer' => 'Yes, all talks will be published online.'],
        ],
        'galleryUrls' => [
            'https://images.unsplash.com/photo-1540575467063-178a50c2df87',
            'https://images.unsplash.com/photo-1511578314322-379afb476865',
        ],
    ];

    $createRes = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/events", $payload);

    $createRes->assertStatus(201)
        ->assertJsonCount(2, 'data.faqs')
        ->assertJsonPath('data.faqs.0.question', 'Is parking free?')
        ->assertJsonCount(2, 'data.galleryUrls');

    $eventUuid = $createRes->json('data.id');

    // Update with modified FAQs and gallery
    $updateRes = $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/organizers/{$organizer->uuid}/events/{$eventUuid}", [
            'faqs' => [
                ['question' => 'Is food provided?', 'answer' => 'Snacks and drinks are complimentary.'],
            ],
            'galleryUrls' => [
                'https://images.unsplash.com/photo-1505373877841-8d25f7d46678',
            ],
        ]);

    $updateRes->assertStatus(200)
        ->assertJsonCount(1, 'data.faqs')
        ->assertJsonPath('data.faqs.0.question', 'Is food provided?')
        ->assertJsonCount(1, 'data.galleryUrls');
});

test('public metadata endpoints return event formats, event types, and categories', function () {
    // 1. Event Formats
    $formatsRes = $this->getJson('/api/v1/meta/event-formats');
    $formatsRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['id', 'name', 'slug'],
            ],
        ]);

    expect(count($formatsRes->json('data')))->toBeGreaterThan(5);

    // 2. Event Types
    $typesRes = $this->getJson('/api/v1/meta/event-types');
    $typesRes->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.key', 'physical');

    // 3. Categories
    $catRes = $this->getJson('/api/v1/meta/categories');
    $catRes->assertStatus(200)
        ->assertJsonPath('success', true);

    expect($catRes->json('data'))->toContain('Technology');
});

test('owner can create an event with separate settings, custom questions, and promoters tables', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Settings Showcase Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $payload = [
        'title' => 'Developers Live 2026',
        'description' => 'A premier engineering gathering.',
        'eventCountry' => 'Nigeria',
        'eventType' => EventType::HYBRID->value,
        'venueName' => 'Landmark Centre',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'scheduleType' => 'single',
        'scheduleTimezone' => 'Africa/Lagos',
        'startDate' => now()->addDays(20)->toDateString(),
        'startTime' => '10:00',
        'endDate' => now()->addDays(20)->toDateString(),
        'endTime' => '18:00',
        'status' => EventStatus::DRAFT->value,
        'visibility' => EventVisibility::PUBLIC->value,

        // Settings fields
        'streamProvider' => 'Google Meet',
        'streamInstructions' => 'The stream link will activate 15 minutes before the event begins.',
        'refundPolicy' => 'Refunds available up to 7 days before event.',
        'refundOption' => '7_days',
        'ageRestriction' => '18+',
        'dressCode' => 'Smart Casual',
        'eventRules' => 'Valid government-issued photo ID required. No professional cameras without prior media accreditation.',
        'amenities' => [
            'parking' => true,
            'rideshare' => true,
            'accessibility' => true,
            'security' => true,
            'medical' => true,
        ],
        'confirmationEmailMsg' => 'Thank you for your order! Please save your QR code passes to your phone for seamless check-in at the entrance.',
        'statusMessage' => 'Ticket sales have ended. Thank you for your interest and support!',
        'isStatusMsgEnabled' => true,
        'statusMessageType' => 'post_sales',
        'preSaleMessage' => 'Tickets selling fast! Gates open 30 minutes prior.',
        'postSaleMessage' => 'Ticket sales have ended. Thank you for your interest and support!',
        'themeColor' => '#E31B23',
        'bannerStyle' => 'cinematic',

        // Custom Questions
        'customQuestions' => [
            [
                'id' => 'q-1',
                'question' => 'How did you hear about this event?',
                'type' => 'Select',
                'options' => 'Instagram, Twitter, Friend, Newsletter',
                'isRequired' => true,
            ],
            [
                'id' => 'q-2',
                'question' => 'Any dietary restrictions or accessibility needs?',
                'type' => 'Text',
                'options' => null,
                'isRequired' => false,
            ],
        ],

        // Promoters
        'promoters' => [
            [
                'id' => 'prm-1',
                'code' => 'EARLY20',
                'discount' => 20,
                'uses' => 45,
                'sales' => 450000,
            ],
            [
                'id' => 'prm-2',
                'code' => 'CREW10',
                'discount' => 10,
                'uses' => 18,
                'sales' => 180000,
            ],
        ],
    ];

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/events", $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Developers Live 2026')
        ->assertJsonPath('data.streamProvider', 'Google Meet')
        ->assertJsonPath('data.refundOption', '7_days')
        ->assertJsonPath('data.themeColor', '#E31B23')
        ->assertJsonPath('data.settings.streamProvider', 'Google Meet')
        ->assertJsonPath('data.settings.refundPolicy', 'Refunds available up to 7 days before event.')
        ->assertJsonPath('data.amenities.parking', true)
        ->assertJsonCount(2, 'data.customQuestions')
        ->assertJsonPath('data.customQuestions.0.id', 'q-1')
        ->assertJsonPath('data.customQuestions.0.question', 'How did you hear about this event?')
        ->assertJsonCount(2, 'data.promoters')
        ->assertJsonPath('data.promoters.0.code', 'EARLY20')
        ->assertJsonPath('data.promoters.0.discount', 20);

    $eventUuid = $response->json('data.id');
    $event = Event::where('uuid', $eventUuid)->firstOrFail();

    // Verify event_settings table
    $this->assertDatabaseHas('event_settings', [
        'event_id' => $event->id,
        'stream_provider' => 'Google Meet',
        'refund_option' => '7_days',
        'theme_color' => '#E31B23',
        'banner_style' => 'cinematic',
    ]);

    // Verify event_custom_questions table
    $this->assertDatabaseHas('event_custom_questions', [
        'event_id' => $event->id,
        'uuid' => 'q-1',
        'question' => 'How did you hear about this event?',
    ]);

    // Verify event_promoters table
    $this->assertDatabaseHas('event_promoters', [
        'event_id' => $event->id,
        'code' => 'EARLY20',
        'discount' => 20.00,
    ]);

    // Test update
    $updateResponse = $this->actingAs($owner, 'sanctum')
        ->putJson("/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}", [
            'themeColor' => '#00FF00',
            'streamProvider' => 'Zoom',
            'promoters' => [
                [
                    'code' => 'SUPERVIP',
                    'discount' => 50,
                    'uses' => 0,
                    'sales' => 0,
                ],
            ],
        ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('data.themeColor', '#00FF00')
        ->assertJsonPath('data.streamProvider', 'Zoom')
        ->assertJsonCount(1, 'data.promoters')
        ->assertJsonPath('data.promoters.0.code', 'SUPERVIP');

    $this->assertDatabaseHas('event_settings', [
        'event_id' => $event->id,
        'theme_color' => '#00FF00',
        'stream_provider' => 'Zoom',
    ]);

    $this->assertDatabaseHas('event_promoters', [
        'event_id' => $event->id,
        'code' => 'SUPERVIP',
    ]);
});

test('duplicating an event clones settings, custom questions, and promoters with reset counts', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Clone Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Original Source Event',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $event->settings()->create([
        'stream_provider' => 'YouTube Live',
        'refund_policy' => 'No refunds',
        'theme_color' => '#123456',
        'amenities' => ['parking' => true],
    ]);

    $event->customQuestions()->create([
        'uuid' => 'q-orig-1',
        'question' => 'T-shirt size?',
        'type' => 'Select',
        'options' => 'S, M, L, XL',
        'is_required' => true,
    ]);

    $event->promoters()->create([
        'uuid' => 'prm-orig-1',
        'code' => 'TESTCODE',
        'discount' => 15,
        'uses' => 50,
        'sales' => 150000,
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/duplicate");

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Original Source Event (Copy)')
        ->assertJsonPath('data.streamProvider', 'YouTube Live')
        ->assertJsonPath('data.themeColor', '#123456')
        ->assertJsonCount(1, 'data.customQuestions')
        ->assertJsonPath('data.customQuestions.0.question', 'T-shirt size?')
        ->assertJsonCount(1, 'data.promoters')
        ->assertJsonPath('data.promoters.0.code', 'TESTCODE')
        ->assertJsonPath('data.promoters.0.uses', 0)
        ->assertJsonPath('data.promoters.0.sales', 0);

    $newEvent = Event::where('uuid', $response->json('data.id'))->firstOrFail();

    $this->assertDatabaseHas('event_settings', [
        'event_id' => $newEvent->id,
        'stream_provider' => 'YouTube Live',
        'theme_color' => '#123456',
    ]);

    $this->assertDatabaseHas('event_promoters', [
        'event_id' => $newEvent->id,
        'code' => 'TESTCODE',
        'uses' => 0,
        'sales' => 0,
    ]);
});
