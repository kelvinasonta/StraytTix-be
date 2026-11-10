<?php

declare(strict_types=1);

namespace Modules\Shared\app\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class FeatureFlagCache
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function getAll(): ?array
    {
        $value = $this->cache->get(
            FeatureFlagCacheKeys::all()
        );

        return is_array($value) ? $value : null;
    }

    public function rememberAll(Closure $callback): array
    {
        $value = $this->cache->remember(
            FeatureFlagCacheKeys::all(),
            FeatureFlagCacheKeys::ttl(),
            $callback,
        );

        return is_array($value) ? $value : [];
    }

    public function putAll(array $features): void
    {
        $this->cache->put(
            FeatureFlagCacheKeys::all(),
            $features,
            FeatureFlagCacheKeys::ttl(),
        );
    }

    public function forget(): void
    {
        $this->cache->forget(
            FeatureFlagCacheKeys::all()
        );
    }

    public function flush(): bool
    {
        return $this->cache->clear();
    }
}
