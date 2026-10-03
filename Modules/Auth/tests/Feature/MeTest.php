<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('me endpoint returns organizer details and role for primary owner', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Owner Events',
        'type' => OrganizerType::INDIVIDUAL,
    ]);

    $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.user.email', $owner->email)
        ->assertJsonPath('data.organizer.name', 'Owner Events')
        ->assertJsonPath('data.organizer.role', 'owner')
        ->assertJsonPath('data.organizer.isOwner', true);
});

test('me endpoint returns organizer details and staff role for staff member', function () {
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $staff = User::factory()->create(['account_type' => 'organizer']);

    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Team Events Ltd',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staff->id,
        'role' => OrganizerMemberRole::ADMIN,
    ]);

    $response = $this->actingAs($staff, 'sanctum')->getJson('/api/v1/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('data.user.email', $staff->email)
        ->assertJsonPath('data.organizer.name', 'Team Events Ltd')
        ->assertJsonPath('data.organizer.role', 'admin')
        ->assertJsonPath('data.organizer.isOwner', false);
});
