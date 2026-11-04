<?php

namespace Modules\Shared\app\Contracts;

use Modules\Shared\app\DTOs\AppSettingsDTO;

interface AppSettingsServiceInterface
{
    /**
     * Get a specific setting value by key, returning a default if not set.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Get all app settings
     */
    public function getAll(): AppSettingsDTO;

    /**
     * Save or update application settings and refresh the cache.
     */
    public function updateSettings(AppSettingsDTO $dto): AppSettingsDTO;

    /**
     * Clear the cached application settings.
     */
    public function clearCache(): void;
}
