<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Modules\Shared\app\Cache\AppSettingsCache;
use Modules\Shared\app\Cache\AppSettingsCacheKeys;
use Modules\Shared\app\Contracts\AppSettingsServiceInterface;
use Modules\Shared\app\DTOs\AppSettingsDTO;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('can retrieve default app settings via api', function () {
    $response = $this->getJson(route('api.app.settings'));

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'App settings retrieved successfully.',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'sitename',
                'site_tagline',
                'favicon',
                'logo',
                'maintenance_message',
                'support_email',
                'timezone',
                'currency_id',
            ],
        ]);
});

test('app settings are stored and cached properly', function () {
    /** @var AppSettingsServiceInterface $service */
    $service = app(AppSettingsServiceInterface::class);

    $dto = new AppSettingsDTO(
        sitename: 'My Amazing Event Platform',
        site_tagline: 'Events for everyone',
        favicon: 'favicon.ico',
        logo: 'logo.png',
        maintenance_message: 'Down for maintenance',
        support_email: 'support@example.com',
        timezone: 'Africa/Lagos',
        currency_id: '1'
    );

    $saved = $service->updateSettings($dto);

    expect($saved->sitename)->toBe('My Amazing Event Platform')
        ->and($saved->timezone)->toBe('Africa/Lagos');

    // Assert DB persistence
    $this->assertDatabaseHas('app_settings', [
        'key' => 'sitename',
        'value' => 'My Amazing Event Platform',
    ]);
    $this->assertDatabaseHas('app_settings', [
        'key' => 'timezone',
        'value' => 'Africa/Lagos',
    ]);

    // Assert cache was populated
    $cache = app(AppSettingsCache::class);
    $cached = $cache->getAll();
    expect($cached)->not->toBeNull()
        ->and($cached['sitename'])->toBe('My Amazing Event Platform');

    // Assert API retrieves the updated cached settings
    $response = $this->getJson(route('api.app.settings'));
    $response->assertOk()
        ->assertJsonPath('data.sitename', 'My Amazing Event Platform')
        ->assertJsonPath('data.timezone', 'Africa/Lagos')
        ->assertJsonPath('data.site_tagline', 'Events for everyone');
});

test('clearing cache forgets cached app settings', function () {
    /** @var AppSettingsServiceInterface $service */
    $service = app(AppSettingsServiceInterface::class);

    $dto = new AppSettingsDTO(
        sitename: 'Cached Site',
        site_tagline: 'Testing Cache Clear'
    );
    $service->updateSettings($dto);

    $cache = app(AppSettingsCache::class);
    expect($cache->getAll())->not->toBeNull();

    $service->clearCache();
    expect(Cache::get(AppSettingsCacheKeys::all()))->toBeNull();
});
