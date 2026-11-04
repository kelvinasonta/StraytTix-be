<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Enum\UserStatus;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('non-admin user cannot access admin routes', function () {
    $regularUser = User::factory()->create([
        'is_admin' => false,
    ]);

    $response = $this->actingAs($regularUser)->getJson('/api/v1/admin/auth/me');

    $response->assertStatus(403)
        ->assertJsonPath('code', 'ADMIN_ACCESS_REQUIRED');
});

test('unauthenticated request to admin routes is rejected', function () {
    $response = $this->getJson('/api/v1/admin/auth/me');

    $response->assertStatus(401);
});

test('admin can log in and receive admin token and profile', function () {
    $admin = User::factory()->create([
        'email' => 'ops@eventsos.internal',
        'password' => 'SecureAdminPass123!',
        'is_admin' => true,
        'admin_role' => 'kyc_reviewer',
        'admin_permissions' => ['organizers.view', 'organizers.verify'],
    ]);

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'ops@eventsos.internal',
        'password' => 'SecureAdminPass123!',
        'device_name' => 'Admin Test Suite',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.admin.email', 'ops@eventsos.internal')
        ->assertJsonPath('data.admin.admin_role', 'kyc_reviewer')
        ->assertJsonStructure([
            'data' => [
                'token',
                'token_type',
                'admin' => ['uuid', 'name', 'email', 'admin_role', 'permissions'],
            ],
        ]);
});

test('regular customer cannot log in through admin portal', function () {
    User::factory()->create([
        'email' => 'customer@eventsos.test',
        'password' => 'CustomerPass123!',
        'is_admin' => false,
    ]);

    $response = $this->postJson('/api/v1/admin/auth/login', [
        'email' => 'customer@eventsos.test',
        'password' => 'CustomerPass123!',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('code', 'ADMIN_ACCESS_REQUIRED');
});

test('admin with insufficient permissions cannot perform restricted action', function () {
    $supportAgent = User::factory()->create([
        'is_admin' => true,
        'admin_role' => 'support_agent',
        'admin_permissions' => ['users.view', 'orders.view'], // Does not have system_settings.manage
    ]);

    $response = $this->actingAs($supportAgent)->postJson('/api/v1/admin/feature-flags/api-rate-limiting/toggle', [
        'is_active' => true,
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('code', 'INSUFFICIENT_ADMIN_PERMISSIONS');
});

test('suspended administrator is barred from accessing admin routes', function () {
    $suspendedAdmin = User::factory()->create([
        'is_admin' => true,
        'status' => UserStatus::SUSPENDED,
        'suspended_at' => now(),
        'suspension_reason' => 'Security review in progress',
    ]);

    $response = $this->actingAs($suspendedAdmin)->getJson('/api/v1/admin/auth/me');

    $response->assertStatus(403)
        ->assertJsonPath('code', 'ADMIN_ACCESS_REQUIRED');
});

test('admin can inspect profile and log out', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'admin_role' => 'super_admin',
        'admin_permissions' => ['*'],
    ]);

    $token = $admin->createToken('admin-test', ['admin:access'])->plainTextToken;

    // Check /auth/me
    $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/auth/me');

    $meResponse->assertStatus(200)
        ->assertJsonPath('data.email', $admin->email)
        ->assertJsonPath('data.admin_role', 'super_admin');

    // Logout
    $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/admin/auth/logout');

    $logoutResponse->assertStatus(200);

    expect($admin->tokens()->count())->toBe(0);
});
