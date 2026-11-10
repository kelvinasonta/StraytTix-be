<?php

declare(strict_types=1);

namespace Modules\Shared\app\Services;

use Laravel\Pennant\Feature;
use Modules\Shared\app\Cache\FeatureFlagCache;
use Modules\Shared\app\Contracts\FeatureFlagServiceInterface;
use Modules\Shared\app\Enum\SystemFeature;

class FeatureFlagService implements FeatureFlagServiceInterface
{
    public function __construct(
        protected readonly FeatureFlagCache $cache,
    ) {}

    /**
     * Retrieve all system features with caching.
     *
     * @return array<string, array{name: string, label: string, description: string, category: string, active: bool}>
     */
    public function getAllFeatures(): array
    {
        return $this->cache->rememberAll(function (): array {
            $features = [];

            foreach (SystemFeature::cases() as $feature) {
                $features[$feature->value] = [
                    'name' => $feature->value,
                    'label' => $feature->label(),
                    'description' => $feature->description(),
                    'category' => $feature->category(),
                    'active' => (bool) Feature::for('global')->active($feature->value),
                ];
            }

            return $features;
        });
    }

    /**
     * Retrieve all features filtered by category (e.g. 'auth', 'system').
     *
     * @return array<string, array{name: string, label: string, description: string, category: string, active: bool}>
     */
    public function getFeaturesByCategory(string $category): array
    {
        return array_filter(
            $this->getAllFeatures(),
            fn (array $item): bool => ($item['category'] ?? '') === $category
        );
    }

    /**
     * Check if a specific feature flag is currently active (cached).
     */
    public function isActive(string $featureName): bool
    {
        $features = $this->getAllFeatures();

        if (isset($features[$featureName])) {
            return (bool) $features[$featureName]['active'];
        }

        return (bool) Feature::for('global')->active($featureName);
    }

    /**
     * Invalidate the global feature flags cache across Redis/Cache drivers.
     */
    public function clearCache(): void
    {
        $this->cache->forget();
        Feature::flushCache();
    }
}
