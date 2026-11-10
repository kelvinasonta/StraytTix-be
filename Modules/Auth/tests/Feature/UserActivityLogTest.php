<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('allows an authenticated user to fetch their own activity logs', function () {
    $user = User::factory()->create(['email' => 'user1@example.com']);
    $otherUser = User::factory()->create(['email' => 'user2@example.com']);

    $auditService = app(AuditService::class);

    // Create logs for $user
    $auditService->log(
        action: 'user.login_success',
        description: 'User logged in successfully.',
        user: $user,
        metadata: ['ip' => '127.0.0.1']
    );

    $auditService->log(
        action: 'user.password.updated',
        description: 'Password was updated.',
        user: $user
    );

    // Create log for $otherUser
    $auditService->log(
        action: 'user.login_success',
        description: 'Other user logged in.',
        user: $otherUser
    );

    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('api.auth.activities'));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'User activities retrieved successfully.',
        ]);

    $data = $response->json('data.data');
    expect($data)->toHaveCount(2);

    $actions = collect($data)->pluck('action')->all();
    expect($actions)->toContain('user.login_success')
        ->toContain('user.password.updated')
        ->not->toContain('Other user logged in.');
});

it('filters user activities by action and search term', function () {
    $user = User::factory()->create();
    $auditService = app(AuditService::class);

    $auditService->log(
        action: 'user.login_success',
        description: 'User logged in.',
        user: $user
    );

    $auditService->log(
        action: 'user.profile.updated',
        description: 'Updated profile details.',
        user: $user
    );

    // Filter by action
    $response = $this->actingAs($user, 'sanctum')
        ->getJson(route('api.auth.activities', ['action' => 'user.profile.updated']));

    $response->assertOk();
    $data = $response->json('data.data');
    expect($data)->toHaveCount(1);
    expect($data[0]['action'])->toBe('user.profile.updated');

    // Filter by search term
    $searchResponse = $this->actingAs($user, 'sanctum')
        ->getJson(route('api.auth.activities', ['search' => 'profile']));

    $searchResponse->assertOk();
    $searchData = $searchResponse->json('data.data');
    expect($searchData)->toHaveCount(1);
    expect($searchData[0]['description'])->toBe('Updated profile details.');
});

it('logs logout action when user logs out', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-device')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.auth.logout'));

    $response->assertOk();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $user->id,
        'action' => 'user.logout',
    ]);
});
