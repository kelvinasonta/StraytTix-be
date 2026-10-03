<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Enum\EventType;
use Modules\Events\app\Enum\EventVisibility;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventStaff;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createTestOrganizerAndEvent(): array
{
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Event Horizon Ltd',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $event = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Lagos Tech Fest 2026',
        'description' => 'Annual developer festival',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Festival',
        'event_category' => 'Technology',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    return [$owner, $organizer, $event];
}

test('organizer owner can assign a team member to an event', function () {
    [$owner, $organizer, $event] = createTestOrganizerAndEvent();

    $staffUser = User::factory()->create();
    $member = OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staffUser->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    $response = $this->actingAs($owner, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/staff",
        [
            'user_id' => $staffUser->id,
            'role' => 'check_in',
            'permissions' => ['scan_tickets', 'view_attendees'],
            'notes' => 'Stationed at Gate A',
        ]
    );

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user_id', $staffUser->id)
        ->assertJsonPath('data.role', 'check_in')
        ->assertJsonPath('data.permissions', ['scan_tickets', 'view_attendees'])
        ->assertJsonPath('data.notes', 'Stationed at Gate A');

    $this->assertDatabaseHas('event_staff', [
        'event_id' => $event->id,
        'user_id' => $staffUser->id,
        'organizer_member_id' => $member->id,
        'role' => 'check_in',
        'notes' => 'Stationed at Gate A',
    ]);
});

test('cannot assign a user who is not a member of the organizer', function () {
    [$owner, $organizer, $event] = createTestOrganizerAndEvent();

    $outsider = User::factory()->create();

    $response = $this->actingAs($owner, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/staff",
        [
            'user_id' => $outsider->id,
            'role' => 'usher',
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);

    $this->assertDatabaseMissing('event_staff', [
        'event_id' => $event->id,
        'user_id' => $outsider->id,
    ]);
});

test('organizer admin can bulk assign multiple team members to an event', function () {
    [$owner, $organizer, $event] = createTestOrganizerAndEvent();

    $adminUser = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $adminUser->id,
        'role' => OrganizerMemberRole::ADMIN,
    ]);

    $staff1 = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staff1->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    $staff2 = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staff2->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    $response = $this->actingAs($adminUser, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/staff",
        [
            'staff' => [
                [
                    'user_id' => $staff1->id,
                    'role' => 'coordinator',
                    'permissions' => ['all'],
                ],
                [
                    'user_id' => $staff2->id,
                    'role' => 'security',
                    'notes' => 'VIP entrance',
                ],
            ],
        ]
    );

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('event_staff', [
        'event_id' => $event->id,
        'user_id' => $staff1->id,
        'role' => 'coordinator',
    ]);

    $this->assertDatabaseHas('event_staff', [
        'event_id' => $event->id,
        'user_id' => $staff2->id,
        'role' => 'security',
    ]);
});

test('staff list can be retrieved for an event', function () {
    [$owner, $organizer, $event] = createTestOrganizerAndEvent();

    $staffUser = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staffUser->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    EventStaff::create([
        'event_id' => $event->id,
        'user_id' => $staffUser->id,
        'assigned_by' => $owner->id,
        'role' => 'usher',
    ]);

    $response = $this->actingAs($owner, 'sanctum')->getJson(
        "/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/staff"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $staffUser->id)
        ->assertJsonPath('data.0.role', 'usher');
});

test('organizer manager can update an event staff assignment', function () {
    [$owner, $organizer, $event] = createTestOrganizerAndEvent();

    $manager = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $manager->id,
        'role' => OrganizerMemberRole::MANAGER,
    ]);

    $staffUser = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staffUser->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    EventStaff::create([
        'event_id' => $event->id,
        'user_id' => $staffUser->id,
        'assigned_by' => $owner->id,
        'role' => 'usher',
    ]);

    $response = $this->actingAs($manager, 'sanctum')->putJson(
        "/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/staff/{$staffUser->id}",
        [
            'role' => 'lead_usher',
            'permissions' => ['manage_ushers'],
            'notes' => 'Promoted to lead',
        ]
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.role', 'lead_usher')
        ->assertJsonPath('data.notes', 'Promoted to lead');

    $this->assertDatabaseHas('event_staff', [
        'event_id' => $event->id,
        'user_id' => $staffUser->id,
        'role' => 'lead_usher',
        'notes' => 'Promoted to lead',
    ]);
});

test('organizer owner can remove staff from an event', function () {
    [$owner, $organizer, $event] = createTestOrganizerAndEvent();

    $staffUser = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staffUser->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    EventStaff::create([
        'event_id' => $event->id,
        'user_id' => $staffUser->id,
        'assigned_by' => $owner->id,
        'role' => 'usher',
    ]);

    $response = $this->actingAs($owner, 'sanctum')->deleteJson(
        "/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/staff/{$staffUser->id}"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('event_staff', [
        'event_id' => $event->id,
        'user_id' => $staffUser->id,
    ]);
});

test('staff role user cannot assign staff to an event', function () {
    [$owner, $organizer, $event] = createTestOrganizerAndEvent();

    $staffUser1 = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staffUser1->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    $staffUser2 = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staffUser2->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    $response = $this->actingAs($staffUser1, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/staff",
        [
            'user_id' => $staffUser2->id,
            'role' => 'usher',
        ]
    );

    $response->assertStatus(403);
});

test('staff members only see events they are assigned to in organizer events list', function () {
    [$owner, $organizer, $event1] = createTestOrganizerAndEvent();

    $event2 = Event::create([
        'organizer_id' => $organizer->id,
        'user_id' => $owner->id,
        'title' => 'Private Executive Gala',
        'description' => 'Invitation-only event',
        'event_country' => 'Nigeria',
        'event_type' => EventType::PHYSICAL,
        'event_format' => 'Gala',
        'event_category' => 'Business',
        'status' => EventStatus::PUBLISHED,
        'visibility' => EventVisibility::PUBLIC,
    ]);

    $staffUser = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staffUser->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    // Assign staff ONLY to event1
    EventStaff::create([
        'event_id' => $event1->id,
        'user_id' => $staffUser->id,
        'assigned_by' => $owner->id,
        'role' => 'usher',
    ]);

    // Staff user requests events list
    $response = $this->actingAs($staffUser, 'sanctum')->getJson(
        "/api/v1/organizers/{$organizer->uuid}/events"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    $returnedEventTitles = collect($response->json('data.data'))->pluck('title');

    expect($returnedEventTitles)->toContain('Lagos Tech Fest 2026')
        ->and($returnedEventTitles)->not->toContain('Private Executive Gala');
});

test('organizer owner can assign a team member to an event using user uuid', function () {
    [$owner, $organizer, $event] = createTestOrganizerAndEvent();

    $staffUser = User::factory()->create();
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staffUser->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    $response = $this->actingAs($owner, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/events/{$event->uuid}/staff",
        [
            'user_id' => $staffUser->uuid,
            'role' => 'check_in',
            'permissions' => ['scan_tickets'],
            'notes' => 'Stationed at Gate A',
        ]
    );

    $response->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('event_staff', [
        'event_id' => $event->id,
        'user_id' => $staffUser->id,
        'role' => 'check_in',
    ]);
});
