<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('authenticated user can create an organizer and is assigned as owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/organizers', [
        'name' => 'Acme Events Inc',
        'type' => OrganizerType::ORGANIZATION->value,
        'description' => 'Leading event organizer',
        'email' => 'contact@acme.com',
        'phone' => '+1234567890',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Acme Events Inc')
        ->assertJsonPath('data.type', 'organization');

    $organizer = Organizer::where('name', 'Acme Events Inc')->first();
    expect($organizer)->not->toBeNull();
    expect($organizer->user_id)->toBe($user->id);

    $member = OrganizerMember::where('organizer_id', $organizer->id)
        ->where('user_id', $user->id)
        ->first();
    expect($member)->not->toBeNull();
    expect($member->role)->toBe(OrganizerMemberRole::OWNER);
});

test('user can list their organizers', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/organizers', [
        'name' => 'First Tech Conference',
        'type' => 'individual',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/organizers');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data.data');
});

test('owner can update their organizer', function () {
    $user = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $user->id,
        'name' => 'Old Name',
        'type' => OrganizerType::INDIVIDUAL,
    ]);

    $response = $this->actingAs($user, 'sanctum')->putJson("/api/v1/organizers/{$organizer->uuid}", [
        'name' => 'Updated Organizer Name',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Organizer Name');
});

test('owner can soft delete their organizer', function () {
    $user = User::factory()->create();
    $organizer = Organizer::create([
        'user_id' => $user->id,
        'name' => 'To Delete',
        'type' => OrganizerType::INDIVIDUAL,
    ]);

    $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/organizers/{$organizer->uuid}");

    $response->assertStatus(200);
    expect(Organizer::find($organizer->id))->toBeNull();
    expect(Organizer::withTrashed()->find($organizer->id))->not->toBeNull();
});

test('can add member to organizer and update role', function () {
    $owner = User::factory()->create();
    $newMemberUser = User::factory()->create();

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Team Organization',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    // Add member
    $response = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/organizers/{$organizer->uuid}/members", [
        'email' => $newMemberUser->email,
        'role' => OrganizerMemberRole::MANAGER->value,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.role', 'manager');

    $member = OrganizerMember::where('organizer_id', $organizer->id)
        ->where('user_id', $newMemberUser->id)
        ->first();

    // Update member role
    $updateResponse = $this->actingAs($owner, 'sanctum')->putJson("/api/v1/organizers/{$organizer->uuid}/members/{$member->id}", [
        'role' => OrganizerMemberRole::ADMIN->value,
    ]);

    $updateResponse->assertStatus(200)
        ->assertJsonPath('data.role', 'admin');

    // Remove member
    $deleteResponse = $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/organizers/{$organizer->uuid}/members/{$member->id}");
    $deleteResponse->assertStatus(200);

    expect(OrganizerMember::find($member->id))->toBeNull();
});

test('admin cannot assign admin or owner role to others', function () {
    $owner = User::factory()->create();
    $adminUser = User::factory()->create();
    $newUser = User::factory()->create();

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'RBAC Organization',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $adminUser->id,
        'role' => OrganizerMemberRole::ADMIN,
    ]);

    // Admin tries to create another Admin (Should fail with 403)
    $response = $this->actingAs($adminUser, 'sanctum')->postJson("/api/v1/organizers/{$organizer->uuid}/members", [
        'email' => $newUser->email,
        'role' => OrganizerMemberRole::ADMIN->value,
    ]);

    $response->assertStatus(403);

    // Admin CAN create a Staff member (Should succeed with 201)
    $staffResponse = $this->actingAs($adminUser, 'sanctum')->postJson("/api/v1/organizers/{$organizer->uuid}/members", [
        'email' => $newUser->email,
        'role' => OrganizerMemberRole::STAFF->value,
    ]);

    $staffResponse->assertStatus(201)
        ->assertJsonPath('data.role', 'staff');

    expect($newUser->fresh()->account_type)->toBe('organizer');
});
