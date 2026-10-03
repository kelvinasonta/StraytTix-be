<?php

namespace App\Services\Cache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

class BaseCacheManager
{
    /**
     * Define default cache duration in hours (e.g., 24 hours).
     */
    protected int $defaultTtlHours = 24;

    /**
     * A unified wrapper for remembering data.
     * Supports both ($key, $callback, $ttl) and Laravel standard ($key, $ttl, $callback).
     */
    public function remember(string $key, mixed $ttlOrCallback, ?Closure $callback = null): mixed
    {
        if ($ttlOrCallback instanceof Closure) {
            $callback = $ttlOrCallback;
            $ttl = now()->addHours($this->defaultTtlHours);
        } else {
            $ttl = $this->resolveTtl($ttlOrCallback);
        }

        $value = Cache::remember($key, $ttl, $callback);
        if ($value instanceof \__PHP_Incomplete_Class) {
            Cache::forget($key);
            $value = $callback();
            Cache::put($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * A unified wrapper for remembering data with tags (with automatic fallback if store does not support tags).
     * Supports both ($tags, $key, $callback, $ttl) and ($tags, $key, $ttl, $callback).
     *
     * @param  array<int, string>|string  $tags
     */
    public function rememberWithTags(
        array|string $tags,
        string $key,
        mixed $ttlOrCallback,
        ?Closure $callback = null
    ): mixed {
        if ($ttlOrCallback instanceof Closure) {
            $callback = $ttlOrCallback;
            $ttl = now()->addHours($this->defaultTtlHours);
        } else {
            $ttl = $this->resolveTtl($ttlOrCallback);
        }

        $tags = (array) $tags;

        if (Cache::supportsTags()) {
            $value = Cache::tags($tags)->remember($key, $ttl, $callback);
            if ($value instanceof \__PHP_Incomplete_Class) {
                Cache::tags($tags)->forget($key);
                $value = $callback();
                Cache::tags($tags)->put($key, $value, $ttl);
            }

            return $value;
        }

        $value = Cache::remember($key, $ttl, $callback);
        if ($value instanceof \__PHP_Incomplete_Class) {
            Cache::forget($key);
            $value = $callback();
            Cache::put($key, $value, $ttl);
        }

        return $value;
    }

    /**
     * A unified wrapper for getting data with a default fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    /**
     * A unified wrapper for getting tagged data with a default fallback.
     *
     * @param  array<int, string>|string  $tags
     */
    public function getWithTags(array|string $tags, string $key, mixed $default = null): mixed
    {
        $tags = (array) $tags;

        if (Cache::supportsTags()) {
            return Cache::tags($tags)->get($key, $default);
        }

        return Cache::get($key, $default);
    }

    /**
     * A unified wrapper for setting data.
     */
    public function put(string $key, mixed $value, mixed $ttl = null): void
    {
        if ($ttl !== null) {
            Cache::put($key, $value, $this->resolveTtl($ttl));
        } else {
            Cache::forever($key, $value);
        }
    }

    /**
     * A unified wrapper for setting tagged data.
     *
     * @param  array<int, string>|string  $tags
     */
    public function putWithTags(array|string $tags, string $key, mixed $value, mixed $ttl = null): void
    {
        $tags = (array) $tags;

        if (Cache::supportsTags()) {
            if ($ttl !== null) {
                Cache::tags($tags)->put($key, $value, $this->resolveTtl($ttl));
            } else {
                Cache::tags($tags)->forever($key, $value);
            }

            return;
        }

        $this->put($key, $value, $ttl);
    }

    /**
     * A unified wrapper for deleting data.
     */
    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * A unified wrapper for flushing cache tags (with fallback key cleanup).
     *
     * @param  array<int, string>|string  $tags
     * @param  array<int, string>  $fallbackKeys
     */
    public function flushTags(array|string $tags, array $fallbackKeys = []): void
    {
        $tags = (array) $tags;

        if (Cache::supportsTags()) {
            Cache::tags($tags)->flush();
        } else {
            foreach ($fallbackKeys as $key) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Resolve TTL into seconds or DateTimeInterface.
     */
    protected function resolveTtl(mixed $ttl): mixed
    {
        if ($ttl instanceof DateTimeInterface || $ttl instanceof DateInterval) {
            return $ttl;
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        return now()->addHours($this->defaultTtlHours);
    }
}
