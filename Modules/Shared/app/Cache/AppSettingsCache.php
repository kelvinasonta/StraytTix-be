<?php

declare(strict_types=1);

namespace Modules\Shared\app\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class AppSettingsCache
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function getAll(): ?array
    {
        $value = $this->cache->get(
            AppSettingsCacheKeys::all()
        );

        return is_array($value) ? $value : null;
    }

    public function rememberAll(Closure $callback): array
    {
        $value = $this->cache->rememberForever(
            AppSettingsCacheKeys::all(),
            $callback,
        );

        return is_array($value) ? $value : [];
    }

    public function putAll(array $settings): void
    {
        $this->cache->forever(
            AppSettingsCacheKeys::all(),
            $settings,
        );
    }

    public function forget(): void
    {
        $this->cache->forget(
            AppSettingsCacheKeys::all()
        );
    }

    public function flush(): bool
    {
        return $this->cache->clear();
    }
}
