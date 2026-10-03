<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerType;
use Modules\Organizer\app\Enum\OrganizerVerificationStatus;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;
use Modules\Organizer\app\Models\OrganizerVerification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createTestOrganizerForVerification(): array
{
    $owner = User::factory()->create(['account_type' => 'organizer']);
    $organizer = Organizer::create([
        'user_id' => $owner->id,
        'name' => 'Apex Event Group Ltd',
        'type' => OrganizerType::ORGANIZATION,
        'verification_status' => OrganizerVerificationStatus::UNVERIFIED,
    ]);

    return [$owner, $organizer];
}

test('organizer owner can request verification with valid business details and documents', function () {
    [$owner, $organizer] = createTestOrganizerForVerification();

    $payload = [
        'business_name' => 'Apex Event Group Limited',
        'business_type' => 'registered_company',
        'registration_number' => 'RC-1234567',
        'tax_id' => 'TIN-987654321',
        'website_url' => 'https://apexevents.ng',
        'official_email' => 'contact@apexevents.ng',
        'official_phone' => '+2348012345678',
        'address' => '12 Marina Road',
        'city' => 'Lagos',
        'state' => 'Lagos',
        'country' => 'Nigeria',
        'documents' => [
            [
                'type' => 'cac_certificate',
                'name' => 'CAC Registration Certificate',
                'url' => 'https://storage.eventsos.ng/documents/cac_apex.pdf',
            ],
            [
                'type' => 'tax_clearance',
                'name' => 'Tax Clearance Certificate',
                'url' => 'https://storage.eventsos.ng/documents/tax_apex.pdf',
            ],
        ],
    ];

    $response = $this->actingAs($owner, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/verification",
        $payload
    );

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.business_name', 'Apex Event Group Limited')
        ->assertJsonPath('data.registration_number', 'RC-1234567')
        ->assertJsonCount(2, 'data.documents');

    $this->assertDatabaseHas('organizer_verifications', [
        'organizer_id' => $organizer->id,
        'requested_by' => $owner->id,
        'status' => 'pending',
        'business_name' => 'Apex Event Group Limited',
        'registration_number' => 'RC-1234567',
    ]);

    expect($organizer->fresh()->verification_status)->toBe(OrganizerVerificationStatus::PENDING);
});

test('organizer admin can also request verification', function () {
    [$owner, $organizer] = createTestOrganizerForVerification();

    $admin = User::factory()->create(['account_type' => 'organizer']);
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $admin->id,
        'role' => OrganizerMemberRole::ADMIN,
    ]);

    $payload = [
        'business_name' => 'Apex Event Group Limited',
        'business_type' => 'registered_company',
        'registration_number' => 'RC-1234567',
        'documents' => [
            [
                'type' => 'cac_certificate',
                'url' => 'https://storage.eventsos.ng/documents/cac_apex.pdf',
            ],
        ],
    ];

    $response = $this->actingAs($admin, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/verification",
        $payload
    );

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('organizer_verifications', [
        'organizer_id' => $organizer->id,
        'requested_by' => $admin->id,
        'status' => 'pending',
    ]);
});

test('staff role cannot submit verification request', function () {
    [$owner, $organizer] = createTestOrganizerForVerification();

    $staff = User::factory()->create(['account_type' => 'organizer']);
    OrganizerMember::create([
        'organizer_id' => $organizer->id,
        'user_id' => $staff->id,
        'role' => OrganizerMemberRole::STAFF,
    ]);

    $payload = [
        'business_name' => 'Apex Event Group Limited',
        'business_type' => 'registered_company',
        'documents' => [
            [
                'type' => 'cac_certificate',
                'url' => 'https://storage.eventsos.ng/documents/cac_apex.pdf',
            ],
        ],
    ];

    $response = $this->actingAs($staff, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/verification",
        $payload
    );

    $response->assertStatus(403);
});

test('cannot submit verification request if one is already pending review', function () {
    [$owner, $organizer] = createTestOrganizerForVerification();

    OrganizerVerification::create([
        'organizer_id' => $organizer->id,
        'requested_by' => $owner->id,
        'status' => OrganizerVerificationStatus::PENDING,
        'business_name' => 'Apex Event Group Limited',
        'business_type' => 'registered_company',
        'documents' => [],
    ]);
    $organizer->update(['verification_status' => OrganizerVerificationStatus::PENDING]);

    $response = $this->actingAs($owner, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/verification",
        [
            'business_name' => 'Another Try',
            'business_type' => 'registered_company',
            'documents' => [
                [
                    'type' => 'cac_certificate',
                    'url' => 'https://storage.eventsos.ng/documents/cac_apex.pdf',
                ],
            ],
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['verification']);
});

test('cannot submit verification request if organization is already verified', function () {
    [$owner, $organizer] = createTestOrganizerForVerification();

    $organizer->update([
        'verification_status' => OrganizerVerificationStatus::VERIFIED,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($owner, 'sanctum')->postJson(
        "/api/v1/organizers/{$organizer->uuid}/verification",
        [
            'business_name' => 'Already Verified Org',
            'business_type' => 'registered_company',
            'documents' => [
                [
                    'type' => 'cac_certificate',
                    'url' => 'https://storage.eventsos.ng/documents/cac_apex.pdf',
                ],
            ],
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['verification']);
});

test('organizer members can view verification status', function () {
    [$owner, $organizer] = createTestOrganizerForVerification();

    $verification = OrganizerVerification::create([
        'organizer_id' => $organizer->id,
        'requested_by' => $owner->id,
        'status' => OrganizerVerificationStatus::PENDING,
        'business_name' => 'Apex Event Group Limited',
        'business_type' => 'registered_company',
        'registration_number' => 'RC-1234567',
        'documents' => [
            ['type' => 'cac_certificate', 'url' => 'https://example.com/cac.pdf'],
        ],
    ]);
    $organizer->update(['verification_status' => OrganizerVerificationStatus::PENDING]);

    $response = $this->actingAs($owner, 'sanctum')->getJson(
        "/api/v1/organizers/{$organizer->uuid}/verification"
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.verification_status', 'pending')
        ->assertJsonPath('data.is_verified', false)
        ->assertJsonPath('data.latest_verification.id', $verification->uuid)
        ->assertJsonPath('data.latest_verification.business_name', 'Apex Event Group Limited');
});

test('platform admin can list verification requests', function () {
    [$owner, $organizer] = createTestOrganizerForVerification();

    OrganizerVerification::create([
        'organizer_id' => $organizer->id,
        'requested_by' => $owner->id,
        'status' => OrganizerVerificationStatus::PENDING,
        'business_name' => 'Apex Event Group Limited',
        'business_type' => 'registered_company',
        'documents' => [],
    ]);

    $adminUser = User::factory()->create();

    $response = $this->actingAs($adminUser, 'sanctum')->getJson('/api/v1/admin/verifications');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.data');
});

test('platform admin can approve verification request', function () {
    [$owner, $organizer] = createTestOrganizerForVerification();

    $verification = OrganizerVerification::create([
        'organizer_id' => $organizer->id,
        'requested_by' => $owner->id,
        'status' => OrganizerVerificationStatus::PENDING,
        'business_name' => 'Apex Event Group Limited',
        'business_type' => 'registered_company',
        'documents' => [],
    ]);
    $organizer->update(['verification_status' => OrganizerVerificationStatus::PENDING]);

    $adminUser = User::factory()->create();

    $response = $this->actingAs($adminUser, 'sanctum')->postJson(
        "/api/v1/admin/verifications/{$verification->uuid}/review",
        [
            'action' => 'approve',
        ]
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'verified');

    expect($organizer->fresh()->verification_status)->toBe(OrganizerVerificationStatus::VERIFIED)
        ->and($organizer->fresh()->verified_at)->not->toBeNull();

    $this->assertDatabaseHas('organizer_verifications', [
        'id' => $verification->id,
        'status' => 'verified',
        'reviewed_by' => $adminUser->id,
    ]);
});

test('platform admin can reject verification request with reason', function () {
    [$owner, $organizer] = createTestOrganizerForVerification();

    $verification = OrganizerVerification::create([
        'organizer_id' => $organizer->id,
        'requested_by' => $owner->id,
        'status' => OrganizerVerificationStatus::PENDING,
        'business_name' => 'Apex Event Group Limited',
        'business_type' => 'registered_company',
        'documents' => [],
    ]);
    $organizer->update(['verification_status' => OrganizerVerificationStatus::PENDING]);

    $adminUser = User::factory()->create();

    $response = $this->actingAs($adminUser, 'sanctum')->postJson(
        "/api/v1/admin/verifications/{$verification->uuid}/review",
        [
            'action' => 'reject',
            'reason' => 'CAC certificate is blurry and unreadable.',
        ]
    );

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'CAC certificate is blurry and unreadable.');

    expect($organizer->fresh()->verification_status)->toBe(OrganizerVerificationStatus::REJECTED);

    $this->assertDatabaseHas('organizer_verifications', [
        'id' => $verification->id,
        'status' => 'rejected',
        'rejection_reason' => 'CAC certificate is blurry and unreadable.',
        'reviewed_by' => $adminUser->id,
    ]);
});
