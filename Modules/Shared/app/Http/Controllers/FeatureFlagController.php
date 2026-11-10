<?php

declare(strict_types=1);

namespace Modules\Shared\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Shared\app\Contracts\FeatureFlagServiceInterface;

class FeatureFlagController extends Controller
{
    public function __construct(
        protected FeatureFlagServiceInterface $featureFlagService
    ) {}

    /**
     * Retrieve public status of all system feature flags (cached).
     */
    public function index(): JsonResponse
    {
        $features = $this->featureFlagService->getAllFeatures();

        return ApiResponse::success('Feature flags retrieved successfully.', $features);
    }
}
