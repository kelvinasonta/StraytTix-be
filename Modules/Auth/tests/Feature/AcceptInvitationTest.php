<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerInvitation;
use Modules\Organizer\app\Models\OrganizerMember;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('guest can verify a valid invitation token', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Acme Events',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $rawToken = Str::random(40);
    $invitation = OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'staff@example.com',
        'role' => OrganizerMemberRole::STAFF,
        'invited_by' => $owner->id,
        'token' => hash('sha256', $rawToken),
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->postJson('/api/v1/auth/invitations/verify', [
        'token' => $rawToken,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'staff@example.com')
        ->assertJsonPath('data.role', OrganizerMemberRole::STAFF->value)
        ->assertJsonPath('data.organizer_name', 'Acme Events');
});

test('verifying an invalid token returns 404', function () {
    $response = $this->postJson('/api/v1/auth/invitations/verify', [
        'token' => 'non-existent-token',
    ]);

    $response->assertStatus(404);
});

test('verifying an expired invitation returns 404', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Acme Events',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $rawToken = Str::random(40);
    OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'staff@example.com',
        'role' => OrganizerMemberRole::STAFF,
        'invited_by' => $owner->id,
        'token' => hash('sha256', $rawToken),
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->postJson('/api/v1/auth/invitations/verify', [
        'token' => $rawToken,
    ]);

    $response->assertStatus(404);
});

test('new user can accept invitation with hashed token stored in database', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Acme Events',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $rawToken = Str::random(40);
    $invitation = OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'newuser@example.com',
        'role' => OrganizerMemberRole::MANAGER,
        'invited_by' => $owner->id,
        'token' => hash('sha256', $rawToken),
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->postJson('/api/v1/auth/invitations/accept', [
        'token' => $rawToken,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['message', 'data' => ['user', 'organizer', 'token']]);

    $user = User::where('email', 'newuser@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->first_name)->toBe('Jane');
    expect($user->last_name)->toBe('Doe');
    expect($user->account_type)->toBe('organizer');

    $member = OrganizerMember::where('organizer_id', $organizer->id)->where('user_id', $user->id)->first();
    expect($member)->not->toBeNull();
    expect($member->role)->toBe(OrganizerMemberRole::MANAGER);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('existing user can accept invitation and joins team', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Acme Events',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $existingUser = User::factory()->create([
        'email' => 'existing@example.com',
        'account_type' => 'customer',
    ]);

    $rawToken = Str::random(40);
    $invitation = OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'existing@example.com',
        'role' => OrganizerMemberRole::STAFF,
        'invited_by' => $owner->id,
        'token' => hash('sha256', $rawToken),
        'expires_at' => now()->addDays(7),
    ]);

    $response = $this->postJson('/api/v1/auth/invitations/accept', [
        'token' => $rawToken,
        'first_name' => $existingUser->first_name,
        'last_name' => $existingUser->last_name,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(200);

    expect($existingUser->fresh()->account_type)->toBe('organizer');
    $member = OrganizerMember::where('organizer_id', $organizer->id)->where('user_id', $existingUser->id)->first();
    expect($member)->not->toBeNull();
    expect($member->role)->toBe(OrganizerMemberRole::STAFF);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('cannot accept an already accepted invitation', function () {
    $owner = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Acme Events',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $rawToken = Str::random(40);
    OrganizerInvitation::create([
        'organizer_id' => $organizer->id,
        'email' => 'staff@example.com',
        'role' => OrganizerMemberRole::STAFF,
        'invited_by' => $owner->id,
        'token' => hash('sha256', $rawToken),
        'expires_at' => now()->addDays(7),
        'accepted_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/auth/invitations/accept', [
        'token' => $rawToken,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertStatus(404);
});
