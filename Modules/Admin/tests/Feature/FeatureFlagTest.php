<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;
use Modules\Auth\app\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->beforeEach(function () {
    Config::set('pennant.default', 'database');
});

it('can toggle a feature flag on and off', function () {
    $admin = User::factory()->create();

    // Default should be off
    Feature::for('global')->deactivate('api-rate-limiting');
    expect(Feature::for('global')->active('api-rate-limiting'))->toBeFalse();

    // Toggle on
    $response = $this->actingAs($admin)->postJson('/api/v1/admin/feature-flags/api-rate-limiting/toggle', [
        'is_active' => true,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'success',
        'is_active' => true,
        'feature' => 'api-rate-limiting',
    ]);

    Feature::flushCache();
    expect(Feature::for('global')->active('api-rate-limiting'))->toBeTrue();

    // Toggle off
    $response = $this->actingAs($admin)->postJson('/api/v1/admin/feature-flags/api-rate-limiting/toggle', [
        'is_active' => false,
    ]);

    $response->assertStatus(200);
    Feature::flushCache();
    expect(Feature::for('global')->active('api-rate-limiting'))->toBeFalse();
});
