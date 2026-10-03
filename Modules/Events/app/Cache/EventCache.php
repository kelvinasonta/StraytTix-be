<?php

declare(strict_types=1);

namespace Modules\Events\App\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class EventCache
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function get(string $uuid): ?array
    {
        $value = $this->cache->get(
            EventCacheKeys::show($uuid)
        );

        return is_array($value) ? $value : null;
    }

    public function remember(
        string $uuid,
        Closure $callback,
    ): array {
        $value = $this->cache->rememberForever(
            EventCacheKeys::show($uuid),
            $callback,
        );

        return $value;
    }

    public function put(
        string $uuid,
        array $event,
    ): void {
        $this->cache->forever(
            EventCacheKeys::show($uuid),
            $event,
        );
    }

    public function forget(string $uuid): void
    {
        $this->cache->forget(
            EventCacheKeys::show($uuid)
        );
    }

    public function forgetOrganizer(string $organizerUuid): void
    {
        $this->cache->forget(
            EventCacheKeys::organizer($organizerUuid)
        );
    }

    public function flush(): bool
    {
        return $this->cache->clear();
    }
}
