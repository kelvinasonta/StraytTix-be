<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Event;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Events\OrganizerInvitationCreatedEvent;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerInvitation;
use Modules\Organizer\app\Models\OrganizerMember;
use Modules\Organizer\app\Services\OrganizerInvitationService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('organizer owner can create an invitation for new staff', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Invite Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $response = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/organizers/{$organizer->uuid}/invitations", [
        'email' => 'newstaff@example.com',
        'role' => OrganizerMemberRole::STAFF->value,
    ]);

    $response->assertStatus(201);

    $invitation = OrganizerInvitation::where('email', 'newstaff@example.com')->first();
    expect($invitation)->not->toBeNull();
    expect($invitation->isPending())->toBeTrue();
});

test('new user can accept invitation and join team', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Join Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $invitation = OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'inviteduser@example.com',
        'role' => OrganizerMemberRole::MANAGER,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->postJson('/api/v1/auth/invitations/accept', [
        'token' => $invitation->token,
        'first_name' => 'Invited',
        'last_name' => 'Person',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['message', 'data' => ['user', 'organizer', 'token']]);

    $user = User::where('email', 'inviteduser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->account_type)->toBe('organizer');

    $member = OrganizerMember::where('organizer_id', $organizer->id)->where('user_id', $user->id)->first();
    expect($member)->not->toBeNull();
    expect($member->role)->toBe(OrganizerMemberRole::MANAGER);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('owner can revoke invitation', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Revoke Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $invitation = OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'torevoke@example.com',
        'role' => OrganizerMemberRole::STAFF,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/organizers/{$organizer->uuid}/invitations/{$invitation->uuid}");

    $response->assertStatus(200);
    expect(OrganizerInvitation::find($invitation->id))->toBeNull();
});

test('invitations are cached and cache is cleared on creation and revocation', function () {
    $service = app(OrganizerInvitationService::class);
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Cache Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $service->createInvitation(
        $organizer,
        $owner,
        OrganizerMemberRole::OWNER,
        'cached1@example.com',
        OrganizerMemberRole::STAFF->value
    );

    $results1 = $service->getAllInvitations($organizer);
    expect($results1->total())->toBe(1);

    // Direct DB insert that bypasses service cache invalidation
    OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'direct-db@example.com',
        'role' => OrganizerMemberRole::STAFF,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    // Should still return cached result of 1 item
    $cachedResults = $service->getAllInvitations($organizer);
    expect($cachedResults->total())->toBe(1);

    // Creating an invitation through service clears the cache
    $service->createInvitation(
        $organizer,
        $owner,
        OrganizerMemberRole::OWNER,
        'cached2@example.com',
        OrganizerMemberRole::STAFF->value
    );

    $freshResults = $service->getAllInvitations($organizer);
    expect($freshResults->total())->toBe(3);
});

test('invitations can be cached and retrieved using file cache driver with serialization', function () {
    config(['cache.default' => 'file']);

    $service = app(OrganizerInvitationService::class);
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'File Cache Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $service->createInvitation(
        $organizer,
        $owner,
        OrganizerMemberRole::OWNER,
        'filecache@example.com',
        OrganizerMemberRole::STAFF->value
    );

    $results1 = $service->getAllInvitations($organizer);
    expect($results1)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($results1->total())->toBe(1);

    $cachedResults = $service->getAllInvitations($organizer);
    expect($cachedResults)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($cachedResults->total())->toBe(1);
    expect($cachedResults->first()->email)->toBe('filecache@example.com');
});

test('organizer invitation created event listener is registered', function () {
    $event = new OrganizerInvitationCreatedEvent([]);
    $listeners = Event::getListeners(get_class($event));
    expect($listeners)->not->toBeEmpty();
});

test('organizer invitation created event is dispatched with correct payload', function () {
    Event::fake([
        OrganizerInvitationCreatedEvent::class,
    ]);

    $service = app(OrganizerInvitationService::class);
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Event Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $service->createInvitation(
        $organizer,
        $owner,
        OrganizerMemberRole::OWNER,
        'invited@example.com',
        OrganizerMemberRole::STAFF->value
    );

    Event::assertDispatched(
        OrganizerInvitationCreatedEvent::class,
        function ($event) use ($organizer) {
            return $event->data['email'] === 'invited@example.com'
                && $event->data['organizer_name'] === $organizer->name
                && ! empty($event->data['invitation_token']);
        }
    );
});

test('owner can resend an invitation and dispatches new invitation event', function () {
    Event::fake([OrganizerInvitationCreatedEvent::class]);

    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Resend Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $oldExpiry = now()->addDay();
    $invitation = OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'resendme@example.com',
        'role' => OrganizerMemberRole::STAFF,
        'invited_by' => $owner->id,
        'expires_at' => $oldExpiry,
    ]);

    $oldToken = $invitation->token;

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/invitations/{$invitation->uuid}/resend");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'resendme@example.com')
        ->assertJsonPath('data.role', OrganizerMemberRole::STAFF->value);

    $invitation->refresh();
    expect($invitation->token)->not->toBe($oldToken);
    expect($invitation->expires_at->gt($oldExpiry))->toBeTrue();

    Event::assertDispatched(
        OrganizerInvitationCreatedEvent::class,
        function ($event) use ($organizer) {
            return $event->data['email'] === 'resendme@example.com'
                && $event->data['organizer_name'] === $organizer->name
                && ! empty($event->data['invitation_token']);
        }
    );
});

test('cannot resend an already accepted invitation', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Resend Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $invitation = OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'alreadyaccepted@example.com',
        'role' => OrganizerMemberRole::STAFF,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->postJson("/api/v1/organizers/{$organizer->uuid}/invitations/{$invitation->uuid}/resend");

    $response->assertStatus(422)
        ->assertJsonPath('code', 'INVITATION_ALREADY_ACCEPTED');
});

test('cannot revoke an already accepted invitation', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Revoke Org',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $invitation = OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'acceptedrevoke@example.com',
        'role' => OrganizerMemberRole::STAFF,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/v1/organizers/{$organizer->uuid}/invitations/{$invitation->uuid}");

    $response->assertStatus(422)
        ->assertJsonPath('code', 'INVITATION_ALREADY_ACCEPTED');
});
