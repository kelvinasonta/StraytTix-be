<?php

declare(strict_types=1);

namespace Modules\Shared\app\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Modules\Shared\app\Contracts\FeatureFlagServiceInterface;
use Modules\Shared\app\Enum\SystemFeature;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureActive
{
    public function __construct(
        protected FeatureFlagServiceInterface $featureFlagService
    ) {}

    /**
     * Handle an incoming request and ensure the specified Pennant feature is active.
     */
    public function handle(Request $request, Closure $next, string $featureName): Response
    {
        if (! $this->featureFlagService->isActive($featureName)) {
            $feature = SystemFeature::tryFrom($featureName);
            $label = $feature?->label() ?? ucwords(str_replace(['-', '_'], ' ', $featureName));

            return ApiResponse::error(
                "{$label} is currently disabled by system administrators.",
                403
            );
        }

        return $next($request);
    }
}
