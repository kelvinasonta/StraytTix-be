<?php

declare(strict_types=1);

namespace Modules\Shared\app\Cache;

final class AppSettingsCacheKeys
{
    private const PREFIX = 'app_settings';

    public static function all(): string
    {
        return self::PREFIX.':all';
    }

    public static function key(string $key): string
    {
        return self::PREFIX.":{$key}";
    }
}
