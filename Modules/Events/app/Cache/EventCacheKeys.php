<?php

declare(strict_types=1);

namespace Modules\Events\app\Cache;

final class EventCacheKeys
{
    private const PREFIX = 'events';

    public static function tag(): string
    {
        return self::PREFIX;
    }

    public static function show(string $uuid): string
    {
        return self::PREFIX.":{$uuid}";
    }

    public static function organizer(string $organizerUuid): string
    {
        return self::PREFIX.":organizer:{$organizerUuid}";
    }

    public static function published(): string
    {
        return self::PREFIX.':published';
    }

    public static function upcoming(): string
    {
        return self::PREFIX.':upcoming';
    }
}
