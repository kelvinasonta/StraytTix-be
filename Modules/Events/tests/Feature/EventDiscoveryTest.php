<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventLocation;
use Modules\Events\app\Models\EventMetric;
use Modules\Events\app\Models\EventSchedule;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Tickets\app\Models\TicketType;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createTestEvent(array $attributes = [], array $locationAttributes = [], array $scheduleAttributes = [], array $metricAttributes = []): Event
{
    static $counter = 1;
    $counter++;

    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = $attributes['organizer'] ?? Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Organizer '.$counter,
        'type' => OrganizerType::ORGANIZATION,
        'is_verified' => true,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => $attributes['title'] ?? 'Test Event '.$counter,
        'slug' => 'test-event-'.$counter.'-'.uniqid(),
        'description' => $attributes['description'] ?? 'A complete deep dive into production systems with sufficient words.',
        'event_country' => 'Nigeria',
        'event_type' => $attributes['event_type'] ?? EventType::PHYSICAL,
        'event_format' => $attributes['event_format'] ?? 'Conference',
        'event_category' => $attributes['event_category'] ?? ['Tech'],
        'status' => $attributes['status'] ?? EventStatus::PUBLISHED,
        'visibility' => $attributes['visibility'] ?? EventVisibility::PUBLIC,
        'banner_url' => $attributes['banner_url'] ?? 'https://example.com/banner.jpg',
        'is_featured' => $attributes['is_featured'] ?? false,
    ]);

    EventLocation::create(array_merge([
        'event_id' => $event->id,
        'venue_name' => 'Landmark Centre',
        'venue_address' => 'Water Corporation Drive',
        'street_address' => 'Victoria Island',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'latitude' => 6.4281,
        'longitude' => 3.4244,
    ], $locationAttributes));

    EventSchedule::create(array_merge([
        'event_id' => $event->id,
        'schedule_type' => 'single',
        'start_date' => Carbon::now()->addDays(5)->toDateString(),
        'end_date' => Carbon::now()->addDays(5)->toDateString(),
        'start_time' => '10:00',
        'end_time' => '18:00',
    ], $scheduleAttributes));

    TicketType::create([
        'event_id' => $event->id,
        'name' => 'General Admission',
        'price' => 5000,
        'capacity' => 100,
        'sale_start' => Carbon::now()->subDay(),
        'sale_end' => Carbon::now()->addDays(5),
    ]);

    EventMetric::create(array_merge([
        'event_id' => $event->id,
        'views_72h' => 100,
        'saves_72h' => 20,
        'shares_72h' => 5,
        'tickets_sold_72h' => 10,
        'total_impressions' => 600, // past exploration
        'quality_score' => 0.85,
        'popularity_score' => 0.60,
    ], $metricAttributes));

    return $event->fresh(['organizer', 'location', 'schedule', 'metrics', 'ticketTypes']);
}

test('discover returns HTTP 200 with ranked events and transparent ranking signals', function () {
    createTestEvent(['title' => 'Lagos Tech Meetup']);

    $response = $this->getJson('/api/v1/events/discover');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'uuid',
                    'title',
                    'slug',
                    'category',
                    'venue' => ['name', 'city', 'state'],
                    'schedule' => ['start_date', 'days_remaining'],
                    'pricing' => ['currency', 'min_price', 'is_free'],
                    'is_sponsored',
                    'ranking' => ['score', 'signals'],
                ],
            ],
            'meta' => ['page', 'per_page', 'returned', 'location_context'],
        ]);
});

test('discover strictly filters by city when city parameter is supplied', function () {
    $phEvent = createTestEvent(
        ['title' => 'Port Harcourt Tech Fest'],
        ['city' => 'Port Harcourt', 'state' => 'Rivers']
    );

    $lagosEvent = createTestEvent(
        ['title' => 'Lagos Coding Night'],
        ['city' => 'Lagos', 'state' => 'Lagos']
    );

    $response = $this->getJson('/api/v1/events/discover?city=Port%20Harcourt');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain('Port Harcourt Tech Fest')
        ->and($titles)->not->toContain('Lagos Coding Night');
});

test('time urgency gives imminent events higher urgency score than events months away', function () {
    // Event in 4 days
    $imminent = createTestEvent(
        ['title' => 'Imminent Event'],
        [],
        ['start_date' => Carbon::now()->addDays(4)->toDateString()]
    );

    // Event in 80 days
    $distant = createTestEvent(
        ['title' => 'Distant Event'],
        [],
        ['start_date' => Carbon::now()->addDays(80)->toDateString()]
    );

    $response = $this->getJson('/api/v1/events/discover');
    $items = collect($response->json('data'));

    $imminentItem = $items->firstWhere('uuid', $imminent->uuid);
    $distantItem = $items->firstWhere('uuid', $distant->uuid);

    expect($imminentItem['ranking']['signals']['urgency_fit'])
        ->toBeGreaterThan($distantItem['ranking']['signals']['urgency_fit']);
});

test('cold-start exploration boost promotes fresh events with low impressions', function () {
    // Brand new event: 10 impressions
    $freshEvent = createTestEvent(
        ['title' => 'Brand New Workshop'],
        [],
        ['start_date' => Carbon::now()->addDays(5)->toDateString()],
        ['total_impressions' => 10, 'popularity_score' => 0.0]
    );

    // Old event: 1500 impressions
    $oldEvent = createTestEvent(
        ['title' => 'Old Event'],
        [],
        ['start_date' => Carbon::now()->addDays(5)->toDateString()],
        ['total_impressions' => 1500, 'popularity_score' => 0.0]
    );

    $response = $this->getJson('/api/v1/events/discover');
    $items = collect($response->json('data'));

    $freshItem = $items->firstWhere('uuid', $freshEvent->uuid);
    $oldItem = $items->firstWhere('uuid', $oldEvent->uuid);

    expect($freshItem['ranking']['signals']['exploration_boost'])
        ->toBeGreaterThan(0.70)
        ->and($oldItem['ranking']['signals']['exploration_boost'])
        ->toEqual(0.0);
});

test('diversification caps an organizer to maximum 2 events in the top 10 positions', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $prolificOrganizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Mega Promotions',
        'type' => OrganizerType::ORGANIZATION,
        'is_verified' => true,
    ]);

    // Create 5 events for this single organizer
    for ($i = 1; $i <= 5; $i++) {
        createTestEvent([
            'organizer' => $prolificOrganizer,
            'title' => "Mega Event {$i}",
            'event_category' => ["Category_{$i}"],
        ]);
    }

    // Create 3 events for other distinct organizers
    for ($i = 1; $i <= 3; $i++) {
        createTestEvent([
            'title' => "Indie Event {$i}",
            'event_category' => ["Category_{$i}"],
        ]);
    }

    $response = $this->getJson('/api/v1/events/discover');
    $topTen = collect($response->json('data'))->take(10);

    $prolificCount = $topTen->filter(fn ($item) => $item['organizer']['id'] === $prolificOrganizer->id)->count();

    expect($prolificCount)->toBeLessThanOrEqual(2);
});

test('sponsored event is placed at slot 1 and transparently marked as sponsored', function () {
    createTestEvent(['title' => 'Regular Organic Event']);

    $sponsoredEvent = createTestEvent([
        'title' => 'Mainland Sponsored Showcase',
        'is_featured' => true,
    ]);

    $response = $this->getJson('/api/v1/events/discover');
    $items = collect($response->json('data'));

    $firstItem = $items->first();

    expect($firstItem['uuid'])->toBe($sponsoredEvent->uuid)
        ->and($firstItem['is_sponsored'])->toBeTrue();
});

test('filters events strictly by category', function () {
    $techEvent = createTestEvent([
        'title' => 'Lagos AI Hackathon',
        'event_category' => ['Technology'],
    ]);

    $musicEvent = createTestEvent([
        'title' => 'Mainland Acoustic Jam',
        'event_category' => ['Music'],
    ]);

    $response = $this->getJson('/api/v1/events/discover?category=Technology');

    $response->assertStatus(200);
    $titles = collect($response->json('data'))->pluck('title');

    expect($titles)->toContain('Lagos AI Hackathon')
        ->and($titles)->not->toContain('Mainland Acoustic Jam');
});

test('supports sorting by upcoming start date', function () {
    $earlyEvent = createTestEvent(
        ['title' => 'Tomorrow Morning Talk'],
        [],
        ['start_date' => Carbon::now()->addDay()->toDateString()]
    );

    $laterEvent = createTestEvent(
        ['title' => 'Next Month Gathering'],
        [],
        ['start_date' => Carbon::now()->addDays(25)->toDateString()]
    );

    $response = $this->getJson('/api/v1/events/discover?sort=upcoming');

    $response->assertStatus(200);
    $data = collect($response->json('data'));

    $earlyIndex = $data->search(fn ($item) => $item['uuid'] === $earlyEvent->uuid);
    $laterIndex = $data->search(fn ($item) => $item['uuid'] === $laterEvent->uuid);

    expect($earlyIndex)->toBeLessThan($laterIndex);
});
