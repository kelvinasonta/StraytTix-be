<?php

declare(strict_types=1);

namespace Modules\Shared\app\Contracts;

interface FeatureFlagServiceInterface
{
    /**
     * Retrieve all system features with caching.
     *
     * @return array<string, array{name: string, label: string, description: string, category: string, active: bool}>
     */
    public function getAllFeatures(): array;

    /**
     * Retrieve all features filtered by category (e.g. 'auth', 'system').
     *
     * @return array<string, array{name: string, label: string, description: string, category: string, active: bool}>
     */
    public function getFeaturesByCategory(string $category): array;

    /**
     * Check if a specific feature flag is currently active (cached).
     */
    public function isActive(string $featureName): bool;

    /**
     * Invalidate the global feature flags cache across Redis/Cache drivers.
     */
    public function clearCache(): void;
}
