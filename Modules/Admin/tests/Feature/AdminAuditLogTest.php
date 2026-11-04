<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'admin_role' => 'platform_admin',
        'admin_permissions' => ['audit_logs.view', 'system_settings.manage'],
    ]);

    $this->auditService = app(AuditService::class);
});

test('admin can retrieve paginated audit logs', function () {
    $this->auditService->log(
        action: 'system.test_action',
        description: 'Test platform audit log description.',
        user: $this->admin,
        metadata: ['ip' => '127.0.0.1'],
        targetType: 'System',
        targetId: 'global',
        reason: 'Periodic sanity check'
    );

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/audit-logs');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'action',
                        'description',
                        'reason',
                        'target_type',
                        'target_id',
                        'created_at',
                        'user',
                    ],
                ],
            ],
        ]);
});

test('admin can filter audit logs by action and target_type', function () {
    $this->auditService->log(
        action: 'event.suspended',
        description: 'Suspended fraudulent event.',
        user: $this->admin,
        targetType: 'Event',
        targetId: 'evt-12345'
    );

    $this->auditService->log(
        action: 'user.suspended',
        description: 'Suspended malicious user account.',
        user: $this->admin,
        targetType: 'User',
        targetId: 'usr-999'
    );

    $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/audit-logs?target_type=Event');

    $response->assertStatus(200);
    $data = $response->json('data.data');

    expect(count($data))->toBe(1);
    expect($data[0]['action'])->toBe('event.suspended');
    expect($data[0]['target_id'])->toBe('evt-12345');
});

test('admin can view single audit log detail', function () {
    $log = $this->auditService->log(
        action: 'feature_flag.toggled',
        description: 'Activated beta payment gateway.',
        user: $this->admin,
        metadata: ['feature' => 'paystack-direct'],
        targetType: 'FeatureFlag',
        targetId: 'paystack-direct'
    );

    $response = $this->actingAs($this->admin)->getJson("/api/v1/admin/audit-logs/{$log->id}");

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $log->id)
        ->assertJsonPath('data.action', 'feature_flag.toggled')
        ->assertJsonPath('data.target_type', 'FeatureFlag');
});

test('admin without audit_logs.view permission cannot access audit logs', function () {
    $limitedAdmin = User::factory()->create([
        'is_admin' => true,
        'admin_role' => 'kyc_reviewer',
        'admin_permissions' => ['organizers.verify'], // Missing audit_logs.view
    ]);

    $response = $this->actingAs($limitedAdmin)->getJson('/api/v1/admin/audit-logs');

    $response->assertStatus(403)
        ->assertJsonPath('code', 'INSUFFICIENT_ADMIN_PERMISSIONS');
});
