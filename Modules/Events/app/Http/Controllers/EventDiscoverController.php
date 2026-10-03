<?php

namespace Modules\Events\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Events\app\DTOs\DiscoverFilterDTO;
use Modules\Events\app\DTOs\UserContextDTO;
use Modules\Events\app\Http\Requests\DiscoverEventsRequest;
use Modules\Events\app\Http\Resources\DiscoverEventResource;
use Modules\Events\app\Services\EventDiscoveryService;

class EventDiscoverController extends Controller
{
    public function __construct(
        protected EventDiscoveryService $discoveryService
    ) {}

    /**
     * Retrieve ranked, personalized, and diversified events for the Discover feed.
     */
    public function index(DiscoverEventsRequest $request): JsonResponse
    {
        $filters = DiscoverFilterDTO::fromArray($request->validated());
        $context = UserContextDTO::fromRequest($request);

        $result = $this->discoveryService->discover($filters, $context);

        return ApiResponse::success(
            message: 'Discover events retrieved successfully.',
            data: DiscoverEventResource::collection($result['data']),
            status: 200,
            meta: $result['meta']
        );
    }
}
