<?php

namespace Modules\Organizer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizer\app\Http\Requests\StoreOrganizerRequest;
use Modules\Organizer\app\Http\Requests\UpdateOrganizerRequest;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Services\OrganizerService;
use Modules\Organizer\app\Transformers\OrganizerResource;

class OrganizerController extends Controller
{
    public function __construct(
        protected OrganizerService $organizerService
    ) {}

    /**
     * Get public details of an organizer by slug or UUID.
     */
    public function showPublic(string $slug): JsonResponse
    {
        $organizer = Organizer::where('slug', $slug)
            ->orWhere('uuid', $slug)
            ->first();

        if (! $organizer) {
            return ApiResponse::error('Organizer not found.', 404);
        }

        return ApiResponse::success(
            'Organizer details retrieved.',
            new OrganizerResource($organizer, true)
        );
    }

    /**
     * Get paginated organizers belonging to or accessible by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $organizers = $this->organizerService->getAllForUser($request->user());

        return ApiResponse::success(
            'Organizers retrieved successfully.',
            OrganizerResource::collection($organizers)->response()->getData(true)
        );
    }

    /**
     * Create a new organization for the authenticated user.
     */
    public function store(StoreOrganizerRequest $request): JsonResponse
    {
        $organizer = $this->organizerService->create($request->user(), $request->validated());

        return ApiResponse::success(
            'Organizer created successfully.',
            new OrganizerResource($organizer, true),
            201
        );
    }

    /**
     * Get details of a specific organization.
     */
    public function show(Request $request, string $organizerUuid): JsonResponse
    {
        $organizer = $this->organizerService->getForUser($request->user(), $organizerUuid);

        return ApiResponse::success(
            'Organizer details retrieved.',
            new OrganizerResource($organizer, true)
        );
    }

    /**
     * Update the authenticated user's organization.
     */
    public function update(UpdateOrganizerRequest $request, string $organizerUuid): JsonResponse
    {
        $organizer = $this->organizerService->update(
            $request->user(),
            $organizerUuid,
            $request->validated()
        );

        return ApiResponse::success(
            'Organizer updated successfully.',
            new OrganizerResource($organizer, true)
        );
    }

    /**
     * Delete an organization.
     */
    public function destroy(Request $request, string $organizerUuid): JsonResponse
    {
        $this->organizerService->delete($request->user(), $organizerUuid);

        return ApiResponse::success('Organizer deleted successfully.');
    }
}
