<?php

namespace Modules\Notification\app\Cache;

use App\Infrastructure\Cache\CacheManager;
use Closure;

class EmailTemplateCache
{
    public function __construct(
        protected CacheManager $cacheManager,
    ) {}

    public function remember(string $slug, Closure $callback, ?int $ttlHours = 1): mixed
    {
        $ttlSeconds = ($ttlHours ?? 1) * 3600;

        return $this->cacheManager->remember("email_template:$slug", $ttlSeconds, $callback);
    }

    public function rememberSetting(Closure $callback, ?int $ttlHours = 1): mixed
    {
        $ttlSeconds = ($ttlHours ?? 1) * 3600;

        return $this->cacheManager->remember('email_template_setting', $ttlSeconds, $callback);
    }

    public function find(string $slug): mixed
    {
        return $this->cacheManager->get("email_template:$slug");
    }

    public function findSetting(): mixed
    {
        return $this->cacheManager->get('email_template_setting');
    }

    public function forget(string $slug): bool
    {
        return $this->cacheManager->forget("email_template:$slug");
    }

    public function forgetSetting(): bool
    {
        return $this->cacheManager->forget('email_template_setting');
    }
}
