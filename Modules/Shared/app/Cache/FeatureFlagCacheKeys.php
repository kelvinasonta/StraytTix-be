<?php

declare(strict_types=1);

namespace Modules\Shared\app\Cache;

final class FeatureFlagCacheKeys
{
    private const PREFIX = 'feature_flags';

    private const TTL_SECONDS = 3600; // 1 hour

    public static function all(): string
    {
        return self::PREFIX.':all';
    }

    public static function key(string $key): string
    {
        return self::PREFIX.":{$key}";
    }

    public static function ttl(): int
    {
        return self::TTL_SECONDS;
    }
}
