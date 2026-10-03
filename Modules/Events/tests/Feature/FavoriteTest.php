<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\Favorite;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['account_type' => 'organizer']);
    $this->organizer = Organizer::create([
        'user_id' => $this->owner->id,
        'name' => 'Tech Summit Hub',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $this->user = User::factory()->create(['account_type' => 'customer']);

    $this->publishedEvent = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Lagos Tech Fest 2026',
        'description' => 'A premier tech gathering.',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Conference',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $this->draftEvent = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Draft Secret Gathering',
        'description' => 'Unpublished event.',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Meetup',
        'status' => EventStatus::DRAFT,
        'visibility' => EventVisibility::PRIVATE,
    ]);
});

test('unauthenticated user cannot access favorites', function () {
    $this->getJson('/api/v1/events/favorites')->assertStatus(401);
    $this->postJson('/api/v1/events/favorites', ['event_id' => $this->publishedEvent->uuid])->assertStatus(401);
});

test('user can add an event to favorites via uuid', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/events/favorites', [
            'event_id' => $this->publishedEvent->uuid,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.favorited', true)
        ->assertJsonPath('data.event_id', $this->publishedEvent->uuid);

    $this->assertDatabaseHas('favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->publishedEvent->id,
    ]);
});

test('adding the same event to favorites is idempotent', function () {
    $this->actingAs($this->user)
        ->postJson('/api/v1/events/favorites', [
            'event_id' => $this->publishedEvent->uuid,
        ])->assertStatus(201);

    // Call again
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/events/favorites', [
            'event_id' => $this->publishedEvent->uuid,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(Favorite::where('user_id', $this->user->id)->where('event_id', $this->publishedEvent->id)->count())->toBe(1);
});

test('adding a non-existent event returns 404', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/events/favorites', [
            'event_id' => 'non-existent-uuid-12345',
        ]);

    $response->assertStatus(404)
        ->assertJsonPath('success', false);
});

test('user can list their favorited events and only published events appear', function () {
    // Favorite both a published event and a draft event
    Favorite::create(['user_id' => $this->user->id, 'event_id' => $this->publishedEvent->id]);
    Favorite::create(['user_id' => $this->user->id, 'event_id' => $this->draftEvent->id]);

    // Another user favorites an event
    $otherUser = User::factory()->create();
    $otherEvent = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Other Event',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);
    Favorite::create(['user_id' => $otherUser->id, 'event_id' => $otherEvent->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/events/favorites');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($this->publishedEvent->uuid)
        ->and($data[0]['title'])->toBe('Lagos Tech Fest 2026');
});

test('user can retrieve favorite event ids', function () {
    Favorite::create(['user_id' => $this->user->id, 'event_id' => $this->publishedEvent->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/events/favorites/ids');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', [$this->publishedEvent->uuid]);
});

test('user can check if an event is favorited', function () {
    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/events/favorites/{$this->publishedEvent->uuid}/check");

    $response->assertStatus(200)
        ->assertJsonPath('data.favorited', false);

    Favorite::create(['user_id' => $this->user->id, 'event_id' => $this->publishedEvent->id]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/v1/events/favorites/{$this->publishedEvent->uuid}/check");

    $response->assertStatus(200)
        ->assertJsonPath('data.favorited', true);
});

test('user can toggle favorite status', function () {
    // First toggle -> favorite added
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/events/favorites/{$this->publishedEvent->uuid}/toggle");

    $response->assertStatus(200)
        ->assertJsonPath('data.favorited', true);

    $this->assertDatabaseHas('favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->publishedEvent->id,
    ]);

    // Second toggle -> favorite removed
    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/events/favorites/{$this->publishedEvent->uuid}/toggle");

    $response->assertStatus(200)
        ->assertJsonPath('data.favorited', false);

    $this->assertDatabaseMissing('favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->publishedEvent->id,
    ]);
});

test('user can remove an event from favorites via DELETE', function () {
    Favorite::create(['user_id' => $this->user->id, 'event_id' => $this->publishedEvent->id]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/v1/events/favorites/{$this->publishedEvent->uuid}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.favorited', false);

    $this->assertDatabaseMissing('favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->publishedEvent->id,
    ]);
});

test('user can sync multiple favorites from guest storage', function () {
    $event2 = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Design Workshop 2026',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/events/favorites/sync', [
            'event_ids' => [
                $this->publishedEvent->uuid,
                $event2->uuid,
                'non-existent-uuid',
            ],
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(Favorite::where('user_id', $this->user->id)->count())->toBe(2);
});

test('favorites endpoints work properly via /api/v1/events/favorites', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/events/favorites', [
            'event_id' => $this->publishedEvent->uuid,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.favorited', true);

    $this->actingAs($this->user)
        ->getJson('/api/v1/events/favorites')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('user can add and remove favorite by numeric id', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/v1/events/favorites', [
            'event_id' => (string) $this->publishedEvent->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->publishedEvent->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/events/favorites/{$this->publishedEvent->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.favorited', false);

    $this->assertDatabaseMissing('favorites', [
        'user_id' => $this->user->id,
        'event_id' => $this->publishedEvent->id,
    ]);
});

test('user can filter favorites by search keyword and paginate results', function () {
    $eventTech = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Flutter Developers Meetup',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $eventMusic = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Afrobeats Night Live',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    Favorite::create(['user_id' => $this->user->id, 'event_id' => $eventTech->id]);
    Favorite::create(['user_id' => $this->user->id, 'event_id' => $eventMusic->id]);

    // Search filter
    $searchRes = $this->actingAs($this->user)
        ->getJson('/api/v1/events/favorites?search=Flutter');

    $searchRes->assertStatus(200);
    $data = $searchRes->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['title'])->toBe('Flutter Developers Meetup');

    // Pagination
    $pageRes = $this->actingAs($this->user)
        ->getJson('/api/v1/events/favorites?perPage=1');

    $pageRes->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ],
        ]);
});
