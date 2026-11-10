<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Pennant\Feature;
use Modules\Shared\app\Enum\SystemFeature;
use Modules\Shared\app\Services\FeatureFlagService;

class AuthFeatureFlagSeeder extends Seeder
{
    /**
     * Seed Pennant feature flags for system & authentication modules.
     */
    public function run(): void
    {
        foreach (SystemFeature::cases() as $systemFeature) {
            if ($systemFeature->defaultActive()) {
                Feature::for('global')->activate($systemFeature->value);
            } else {
                Feature::for('global')->deactivate($systemFeature->value);
            }
        }

        app(FeatureFlagService::class)->clearCache();
    }
}
