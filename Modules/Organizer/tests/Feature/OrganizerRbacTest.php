<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventCollaboratorRole;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventCollaborator;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Events\OrganizerInvitationCreatedEvent;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerInvitation;
use Modules\Organizer\app\Models\OrganizerMember;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->organizer = Organizer::create([
        'user_id' => $this->owner->id,
        'name' => 'Acme Organization',
        'type' => 'organization',
    ]);
});

test('organizer owner has wildcard access and can delete organization', function () {
    expect(Gate::forUser($this->owner)->allows('update', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('delete', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('manageMembers', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($this->owner)->allows('manageFinances', $this->organizer))->toBeTrue();
});

test('organizer admin can manage members and finances but cannot delete organization', function () {
    $adminUser = User::factory()->create();
    OrganizerMember::create([
        'user_id' => $adminUser->id,
        'organizer_id' => $this->organizer->id,
        'role' => OrganizerMemberRole::ADMIN,
    ]);

    expect(Gate::forUser($adminUser)->allows('update', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($adminUser)->allows('manageMembers', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($adminUser)->allows('manageFinances', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($adminUser)->allows('delete', $this->organizer))->toBeFalse();
});

test('finance member can manage finances but cannot delete org or manage members', function () {
    $financeUser = User::factory()->create();
    OrganizerMember::create([
        'user_id' => $financeUser->id,
        'organizer_id' => $this->organizer->id,
        'role' => OrganizerMemberRole::FINANCE,
    ]);

    expect(Gate::forUser($financeUser)->allows('manageFinances', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($financeUser)->allows('viewFinances', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($financeUser)->allows('manageCredits', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($financeUser)->allows('manageMembers', $this->organizer))->toBeFalse()
        ->and(Gate::forUser($financeUser)->allows('delete', $this->organizer))->toBeFalse()
        ->and(Gate::forUser($financeUser)->allows('update', $this->organizer))->toBeFalse();
});

test('manager can manage events but cannot access finances or delete organization', function () {
    $managerUser = User::factory()->create();
    OrganizerMember::create([
        'user_id' => $managerUser->id,
        'organizer_id' => $this->organizer->id,
        'role' => OrganizerMemberRole::MANAGER,
    ]);

    expect(Gate::forUser($managerUser)->allows('manageFinances', $this->organizer))->toBeFalse()
        ->and(Gate::forUser($managerUser)->allows('manageMembers', $this->organizer))->toBeFalse()
        ->and(Gate::forUser($managerUser)->allows('delete', $this->organizer))->toBeFalse();
});

test('staff member can view organization profile but cannot perform mutations', function () {
    $staffUser = User::factory()->create();
    OrganizerMember::create([
        'user_id' => $staffUser->id,
        'organizer_id' => $this->organizer->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    expect(Gate::forUser($staffUser)->allows('view', $this->organizer))->toBeTrue()
        ->and(Gate::forUser($staffUser)->allows('update', $this->organizer))->toBeFalse()
        ->and(Gate::forUser($staffUser)->allows('delete', $this->organizer))->toBeFalse()
        ->and(Gate::forUser($staffUser)->allows('manageFinances', $this->organizer))->toBeFalse()
        ->and(Gate::forUser($staffUser)->allows('manageMembers', $this->organizer))->toBeFalse();
});

test('event collaborator scanner can only scan tickets for assigned event', function () {
    $scannerUser = User::factory()->create();
    $event = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Event 1',
        'event_country' => 'Nigeria',
        'event_type' => 'physical',
        'slug' => 'event-1-'.Str::uuid(),
    ]);
    $unrelatedEvent = Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'Event 2',
        'event_country' => 'Nigeria',
        'event_type' => 'physical',
        'slug' => 'event-2-'.Str::uuid(),
    ]);

    EventCollaborator::create([
        'event_id' => $event->id,
        'user_id' => $scannerUser->id,
        'role' => EventCollaboratorRole::CHECK_IN_AGENT,
    ]);

    expect(Gate::forUser($scannerUser)->allows('scanTickets', $event))->toBeTrue()
        ->and(Gate::forUser($scannerUser)->allows('viewAttendees', $event))->toBeTrue()
        ->and(Gate::forUser($scannerUser)->allows('update', $event))->toBeFalse()
        ->and(Gate::forUser($scannerUser)->allows('delete', $event))->toBeFalse()
        ->and(Gate::forUser($scannerUser)->allows('scanTickets', $unrelatedEvent))->toBeFalse();
});

test('owner permissions payload includes both wildcard and all explicit permission strings', function () {
    $this->owner->update(['email_verified_at' => now()]);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->getJson('/api/v1/auth/me');

    $response->assertSuccessful()
        ->assertJsonPath('data.organizer.role', 'owner')
        ->assertJsonPath('data.organizer.isOwner', true);

    $permissions = $response->json('data.organizer.permissions');
    expect($permissions)->toContain('*')
        ->and($permissions)->toContain('org.events.create')
        ->and($permissions)->toContain('org.members.manage')
        ->and($permissions)->toContain('org.finance.manage')
        ->and($permissions)->toContain('org.delete');
});

test('invited manager can load dashboard endpoints without 403 and cannot mutate protected settings', function () {
    $manager = User::factory()->create(['email_verified_at' => now(), 'account_type' => 'organizer']);
    OrganizerMember::create([
        'user_id' => $manager->id,
        'organizer_id' => $this->organizer->id,
        'role' => OrganizerMemberRole::MANAGER,
    ]);

    // 1. /auth/me resolves the invited organization and manager role
    $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.organizer.id', $this->organizer->uuid)
        ->assertJsonPath('data.organizer.role', 'manager')
        ->assertJsonPath('data.organizer.isOwner', false);

    // 2. Dashboard read endpoints succeed
    $this->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}")
        ->assertSuccessful()
        ->assertJsonPath('data.role', 'manager');

    $this->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/verification")
        ->assertSuccessful();

    $this->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/orders")
        ->assertSuccessful();

    $this->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/members")
        ->assertSuccessful();

    $this->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/roles")
        ->assertSuccessful()
        ->assertJsonPath('data.current_role', 'manager');

    // 3. Manager cannot mutate organization profile, bank account, or delete org
    $this->actingAs($manager, 'sanctum')
        ->putJson("/api/v1/organizers/{$this->organizer->uuid}", ['name' => 'Hacked'])
        ->assertForbidden();

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/bank-account", [
            'bank_name' => 'Access Bank',
            'bank_code' => '044',
            'account_number' => '0123456789',
            'account_name' => 'Test Account',
        ])
        ->assertForbidden();

    $this->actingAs($manager, 'sanctum')
        ->deleteJson("/api/v1/organizers/{$this->organizer->uuid}")
        ->assertForbidden();
});

test('organization staff can view organization events when no per-event staff assignments exist', function () {
    $staffUser = User::factory()->create(['email_verified_at' => now(), 'account_type' => 'organizer']);
    OrganizerMember::create([
        'user_id' => $staffUser->id,
        'organizer_id' => $this->organizer->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    Event::create([
        'organizer_id' => $this->organizer->id,
        'user_id' => $this->owner->id,
        'title' => 'General Organization Event',
        'event_country' => 'Nigeria',
        'event_type' => 'physical',
        'slug' => 'general-org-event-'.Str::uuid(),
    ]);

    $response = $this->actingAs($staffUser, 'sanctum')
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/events");

    $response->assertSuccessful();
    $titles = collect($response->json('data.data'))->pluck('title');
    expect($titles)->toContain('General Organization Event');
});

test('existing user can accept invitation with token only and re-inviting same email updates invitation', function () {
    Illuminate\Support\Facades\Event::fake([
        OrganizerInvitationCreatedEvent::class,
    ]);

    User::factory()->create([
        'email' => 'reinvite@example.com',
    ]);

    // First invitation
    $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/invitations", [
            'email' => 'reinvite@example.com',
            'role' => OrganizerMemberRole::STAFF->value,
        ])
        ->assertCreated();

    // Re-inviting the same email with updated role (e.g. manager) updates existing row without unique constraint error
    $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/v1/organizers/{$this->organizer->uuid}/invitations", [
            'email' => 'reinvite@example.com',
            'role' => OrganizerMemberRole::MANAGER->value,
        ])
        ->assertCreated();

    $invitation = OrganizerInvitation::where('email', 'reinvite@example.com')->first();
    expect($invitation->role)->toBe(OrganizerMemberRole::MANAGER);

    $plainToken = null;
    Illuminate\Support\Facades\Event::assertDispatched(
        OrganizerInvitationCreatedEvent::class,
        function ($event) use (&$plainToken) {
            if ($event->data['email'] === 'reinvite@example.com') {
                $plainToken = $event->data['invitation_token'];

                return true;
            }

            return false;
        }
    );

    // Existing user accepts invitation without needing to re-submit first_name, last_name, password
    $acceptResponse = $this->postJson('/api/v1/auth/invitations/accept', [
        'token' => $plainToken,
    ]);

    $acceptResponse->assertSuccessful()
        ->assertJsonPath('data.organizer.role', 'manager')
        ->assertJsonPath('data.user.email', 'reinvite@example.com');
});

it('returns supported countries on GET /api/v1/countries and GET /api/v1/meta/countries', function () {
    $this->getJson('/api/v1/countries')
        ->assertSuccessful()
        ->assertJsonPath('data.0.code', 'NG')
        ->assertJsonPath('data.0.currency', 'NGN');

    $this->getJson('/api/v1/meta/countries')
        ->assertSuccessful()
        ->assertJsonPath('data.0.code', 'NG');
});

it('returns payout summary on GET /api/v1/payments/payouts and GET /api/v1/organizers/{uuid}/payouts with RBAC enforcement', function () {
    $adminUser = User::factory()->create();
    OrganizerMember::create([
        'user_id' => $adminUser->id,
        'organizer_id' => $this->organizer->id,
        'role' => OrganizerMemberRole::ADMIN,
    ]);

    $financeUser = User::factory()->create();
    OrganizerMember::create([
        'user_id' => $financeUser->id,
        'organizer_id' => $this->organizer->id,
        'role' => OrganizerMemberRole::FINANCE,
    ]);

    // Owner, Admin, Finance can access payouts
    foreach ([$this->owner, $adminUser, $financeUser] as $authorizedUser) {
        $this->actingAs($authorizedUser, 'sanctum')
            ->getJson('/api/v1/payments/payouts')
            ->assertSuccessful()
            ->assertJsonPath('data.organizer_uuid', $this->organizer->uuid)
            ->assertJsonStructure([
                'data' => [
                    'organizer_uuid',
                    'available_balance',
                    'total_revenue',
                    'pending_balance',
                    'total_paid_out',
                    'currency',
                    'summary',
                    'bank_account',
                    'payouts',
                ],
            ]);

        $this->actingAs($authorizedUser, 'sanctum')
            ->getJson("/api/v1/organizers/{$this->organizer->uuid}/payouts")
            ->assertSuccessful()
            ->assertJsonPath('data.organizer_uuid', $this->organizer->uuid);
    }

    // Outsider cannot access organizer payouts via query param or organizer route
    $outsider = User::factory()->create();
    $this->actingAs($outsider, 'sanctum')
        ->getJson("/api/v1/payments/payouts?organizerUuid={$this->organizer->uuid}")
        ->assertForbidden();

    $this->actingAs($outsider, 'sanctum')
        ->getJson("/api/v1/organizers/{$this->organizer->uuid}/payouts")
        ->assertForbidden();
});
