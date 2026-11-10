<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Pennant\Feature;
use Modules\Auth\app\Models\User;
use Modules\Shared\app\Enum\SystemFeature;
use Modules\Shared\app\Services\FeatureFlagService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Feature::flushCache();
    Feature::purge();
    app(FeatureFlagService::class)->clearCache();
});

it('exposes public auth feature flags on GET /api/v1/auth/features', function () {
    $response = $this->getJson('/api/v1/auth/features');

    $response->assertSuccessful()
        ->assertJsonPath('data.auth-registration.active', true)
        ->assertJsonPath('data.auth-password-reset.active', true)
        ->assertJsonPath('data.auth-session-management.active', true)
        ->assertJsonPath('data.auth-social-login.active', false)
        ->assertJsonPath('data.auth-two-factor.active', false);
});

it('exposes system feature flags on GET /api/v1/features', function () {
    $response = $this->getJson('/api/v1/features');

    $response->assertSuccessful()
        ->assertJsonPath('data.auth-registration.active', true)
        ->assertJsonPath('data.api-rate-limiting.active', true);
});

it('blocks registration when auth-registration feature is deactivated', function () {
    Feature::for('global')->deactivate(SystemFeature::AUTH_REGISTRATION->value);
    app(FeatureFlagService::class)->clearCache();

    $response = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'featureflag@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type' => 'customer',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'User Registration is currently disabled by system administrators.');

    // Re-enable and verify it works
    Feature::for('global')->activate(SystemFeature::AUTH_REGISTRATION->value);
    app(FeatureFlagService::class)->clearCache();

    $validResponse = $this->postJson('/api/v1/auth/register', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'featureflag@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'account_type' => 'customer',
    ]);

    $validResponse->assertCreated();
});

it('blocks password reset when auth-password-reset is deactivated', function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    $user = User::factory()->create([
        'email' => 'resetme@example.com',
    ]);

    Feature::for('global')->deactivate(SystemFeature::AUTH_PASSWORD_RESET->value);
    app(FeatureFlagService::class)->clearCache();

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => $user->email,
    ])->assertForbidden()
        ->assertJsonPath('message', 'Password Reset is currently disabled by system administrators.');

    Feature::for('global')->activate(SystemFeature::AUTH_PASSWORD_RESET->value);
    app(FeatureFlagService::class)->clearCache();

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => $user->email,
    ])->assertSuccessful();
});

it('blocks session management endpoints when auth-session-management is deactivated', function () {
    $user = User::factory()->create();

    Feature::for('global')->deactivate(SystemFeature::AUTH_SESSION_MANAGEMENT->value);
    app(FeatureFlagService::class)->clearCache();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/sessions')
        ->assertForbidden()
        ->assertJsonPath('message', 'Session & Device Management is currently disabled by system administrators.');

    Feature::for('global')->activate(SystemFeature::AUTH_SESSION_MANAGEMENT->value);
    app(FeatureFlagService::class)->clearCache();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/sessions')
        ->assertSuccessful();
});

it('includes auth features in GET /api/v1/auth/me response', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                'user',
                'features' => [
                    'auth-registration',
                    'auth-password-reset',
                    'auth-session-management',
                    'auth-social-login',
                    'auth-two-factor',
                    'api-rate-limiting',
                ],
            ],
        ]);
});
