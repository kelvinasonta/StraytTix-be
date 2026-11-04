<?php

declare(strict_types=1);

namespace Modules\Shared\app\Services;

use Modules\Shared\app\Cache\AppSettingsCache;
use Modules\Shared\app\Contracts\AppSettingsServiceInterface;
use Modules\Shared\app\DTOs\AppSettingsDTO;
use Modules\Shared\app\Models\AppSettings;

class AppSettingsService implements AppSettingsServiceInterface
{
    public function __construct(
        protected readonly AppSettingsCache $cache,
    ) {}

    /**
     * Get a specific setting value by key, returning a default if not set.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->getAll()->toArray();

        return $settings[$key] ?? $default;
    }

    /**
     * Get all app settings.
     */
    public function getAll(): AppSettingsDTO
    {
        $cached = $this->cache->rememberAll(function (): array {
            return AppSettings::query()
                ->pluck('value', 'key')
                ->all();
        });

        return AppSettingsDTO::fromArray($cached);
    }

    /**
     * Save or update application settings and refresh the cache.
     */
    public function updateSettings(AppSettingsDTO $dto): AppSettingsDTO
    {
        $data = $dto->toArray();

        foreach ($data as $key => $value) {
            AppSettings::updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) ($value ?? ''),
                    'group' => 'general',
                    'type' => 'string',
                    'is_public' => true,
                ],
            );
        }

        $this->cache->putAll($data);

        return $dto;
    }

    /**
     * Clear the cached application settings.
     */
    public function clearCache(): void
    {
        $this->cache->forget();
    }
}
