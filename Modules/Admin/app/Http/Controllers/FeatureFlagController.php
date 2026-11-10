<?php

declare(strict_types=1);

namespace Modules\Admin\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Modules\Audit\app\Services\AuditService;
use Modules\Shared\app\Services\FeatureFlagService;

class FeatureFlagController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected FeatureFlagService $featureFlagService
    ) {}

    /**
     * Toggle a feature flag.
     */
    public function toggle(Request $request, string $feature): JsonResponse
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $isActive = $request->boolean('is_active');

        if ($isActive) {
            Feature::for('global')->activate($feature);
        } else {
            Feature::for('global')->deactivate($feature);
        }

        // Invalidate global persistent cache
        $this->featureFlagService->clearCache();

        $this->auditService->log(
            action: 'feature_flag.toggled',
            description: "Feature flag '{$feature}' was ".($isActive ? 'activated' : 'deactivated').' globally.',
            user: $request->user(),
            metadata: ['feature' => $feature, 'is_active' => $isActive],
            targetType: 'FeatureFlag',
            targetId: $feature
        );

        return response()->json([
            'status' => 'success',
            'message' => "Feature flag '{$feature}' has been ".($isActive ? 'activated' : 'deactivated').' for everyone.',
            'feature' => $feature,
            'is_active' => $isActive,
        ]);
    }
}
