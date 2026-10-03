<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('owner and manager can view audit logs for their organizer', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Tech Conf Hub',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $auditService = app(AuditService::class);
    $auditService->log(
        action: 'event.created',
        description: "Event 'AI Summit' was created",
        organizer: $organizer,
        user: $owner,
        metadata: ['title' => 'AI Summit']
    );

    $auditService->log(
        action: 'event.published',
        description: "Event 'AI Summit' was published",
        organizer: $organizer,
        user: $owner,
        metadata: ['title' => 'AI Summit']
    );

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/organizers/{$organizer->uuid}/audit-logs");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data.data')
        ->assertJsonPath('data.data.0.action', 'event.published')
        ->assertJsonPath('data.data.1.action', 'event.created')
        ->assertJsonPath('data.data.1.user.name', $owner->name);
});

test('audit logs can be filtered by action and searched', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Global Events Inc',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $auditService = app(AuditService::class);
    $auditService->log('event.created', 'Event Alpha was created', $organizer, $owner);
    $auditService->log('event.updated', 'Event Beta was modified', $organizer, $owner);
    $auditService->log('member.invited', 'Invited alice@example.com', $organizer, $owner);

    // Filter by action
    $filterResponse = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/organizers/{$organizer->uuid}/audit-logs?action=event.created");

    $filterResponse->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.action', 'event.created');

    // Search by keyword
    $searchResponse = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/organizers/{$organizer->uuid}/audit-logs?search=alice");

    $searchResponse->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.action', 'member.invited');
});

test('user can view specific audit log details', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Festival Ops',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $auditService = app(AuditService::class);
    $log = $auditService->log(
        action: 'event.deleted',
        description: "Event 'Old Festival' was deleted",
        organizer: $organizer,
        user: $owner,
        metadata: ['eventId' => 99]
    );

    $response = $this->actingAs($owner, 'sanctum')
        ->getJson("/api/v1/organizers/{$organizer->uuid}/audit-logs/{$log->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.action', 'event.deleted')
        ->assertJsonPath('data.description', "Event 'Old Festival' was deleted")
        ->assertJsonPath('data.metadata.eventId', 99);
});

test('unauthorized users and non-members cannot view organizer audit logs', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $stranger = User::factory()->create(['account_type' => 'organizer']);

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Secret Summits',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    // Stranger cannot view
    $this->actingAs($stranger, 'sanctum')
        ->getJson("/api/v1/organizers/{$organizer->uuid}/audit-logs")
        ->assertStatus(403);
});

test('staff role cannot access audit logs', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $staff = User::factory()->create(['account_type' => 'organizer']);

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Conference Co',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staff->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    $this->actingAs($staff, 'sanctum')
        ->getJson("/api/v1/organizers/{$organizer->uuid}/audit-logs")
        ->assertStatus(403);
});
