<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Enum\OrganizerVerificationStatus;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerVerification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'account_type' => 'customer',
        'is_admin' => true,
        'admin_role' => 'super_admin',
        'admin_permissions' => ['*'],
    ]);
    $this->organizerOwner = User::factory()->create(['account_type' => 'organizer']);

    $this->organizer = Organizer::create([
        'user_id' => $this->organizerOwner->id,
        'name' => 'African Tech Summit',
        'type' => OrganizerType::ORGANIZATION,
    ]);

    $this->verification = OrganizerVerification::create([
        'organizer_id' => $this->organizer->id,
        'requested_by' => $this->organizerOwner->id,
        'status' => OrganizerVerificationStatus::PENDING,
        'business_name' => 'African Tech Summit Ltd',
        'business_type' => 'Company',
        'registration_number' => 'RC-998877',
        'tax_id' => 'TAX-112233',
        'website_url' => 'https://africatechsummit.com',
        'official_email' => 'contact@africatechsummit.com',
        'official_phone' => '+2348001122334',
        'address' => '10 Marina Street',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'country' => 'Nigeria',
    ]);
});

test('admin can list pending verification requests', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/verifications?status=pending');

    $response->assertStatus(200)
        ->assertJsonPath('success', true);

    expect(count($response->json('data.data')))->toBeGreaterThanOrEqual(1);
});

test('admin can view verification request details', function () {
    $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/verifications/{$this->verification->uuid}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.business_name', 'African Tech Summit Ltd')
        ->assertJsonPath('data.registration_number', 'RC-998877');
});

test('admin can approve a verification request', function () {
    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/verifications/{$this->verification->uuid}/review", [
        'action' => 'approve',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'verified');

    $this->verification->refresh();
    expect($this->verification->status)->toBe(OrganizerVerificationStatus::VERIFIED);
    expect($this->organizer->fresh()->isVerified())->toBeTrue();
});

test('admin can reject a verification request with a reason', function () {
    $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/verifications/{$this->verification->uuid}/review", [
        'action' => 'reject',
        'reason' => 'Business registration certificate is illegible. Please re-upload.',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'rejected');

    $this->verification->refresh();
    expect($this->verification->status)->toBe(OrganizerVerificationStatus::REJECTED);
    expect($this->verification->rejection_reason)->toBe('Business registration certificate is illegible. Please re-upload.');
    expect($this->organizer->fresh()->isVerified())->toBeFalse();
});
