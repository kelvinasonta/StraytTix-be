<?php

namespace Modules\Events\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Events\app\Services\FavoriteService;
use Modules\Events\app\Transformers\EventResource;

class FavouriteController extends Controller
{
    public function __construct(
        protected FavoriteService $favoriteService
    ) {}

    /**
     * List all published events favorited by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $events = $this->favoriteService->getFavorites($request->user(), $request->only([
            'search',
            'eventType',
            'category',
            'perPage',
            'per_page',
        ]));

        $data = $events instanceof LengthAwarePaginator
            ? EventResource::collection($events)->response()->getData(true)
            : EventResource::collection($events);

        return ApiResponse::success('Favorite events retrieved successfully.', $data);
    }

    /**
     * Retrieve all favorited event UUIDs for fast client caching.
     */
    public function ids(Request $request): JsonResponse
    {
        $ids = $this->favoriteService->getFavoriteIds($request->user());

        return ApiResponse::success('Favorite event IDs retrieved successfully.', $ids);
    }

    /**
     * Add an event to favorites.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => 'required_without_all:eventId,event_uuid|nullable|string',
            'eventId' => 'nullable|string',
            'event_uuid' => 'nullable|string',
        ]);

        $eventIdentifier = $request->input('event_id')
            ?? $request->input('eventId')
            ?? $request->input('event_uuid');

        try {
            $favorite = $this->favoriteService->addFavorite($request->user(), $eventIdentifier);

            return ApiResponse::success('Event added to favorites successfully.', [
                'favorited' => true,
                'event_id' => $eventIdentifier,
                'favorite_id' => $favorite->uuid,
                'event' => $favorite->event ? new EventResource($favorite->event) : null,
            ], 201);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Event not found.', 404, 'EVENT_NOT_FOUND');
        }
    }

    /**
     * Remove an event from favorites.
     */
    public function destroy(Request $request, ?string $eventUuid = null): JsonResponse
    {
        $eventIdentifier = $eventUuid
            ?? $request->input('event_id')
            ?? $request->input('eventId');

        if (! $eventIdentifier) {
            return ApiResponse::error('Event identifier is required.', 422, 'VALIDATION_ERROR');
        }

        $this->favoriteService->deleteFavorite($request->user(), $eventIdentifier);

        return ApiResponse::success('Event removed from favorites successfully.', [
            'favorited' => false,
            'event_id' => $eventIdentifier,
        ]);
    }

    /**
     * Toggle favorite status of an event.
     */
    public function toggle(Request $request, ?string $eventUuid = null): JsonResponse
    {
        $eventIdentifier = $eventUuid
            ?? $request->input('event_id')
            ?? $request->input('eventId');

        if (! $eventIdentifier) {
            return ApiResponse::error('Event identifier is required.', 422, 'VALIDATION_ERROR');
        }

        try {
            $result = $this->favoriteService->toggleFavorite($request->user(), $eventIdentifier);

            return ApiResponse::success($result['message'], [
                'favorited' => $result['favorited'],
                'event_id' => $eventIdentifier,
            ]);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Event not found.', 404, 'EVENT_NOT_FOUND');
        }
    }

    /**
     * Check if a specific event is in the user's favorites.
     */
    public function check(Request $request, string $eventUuid): JsonResponse
    {
        $isFavorited = $this->favoriteService->isFavorited($request->user(), $eventUuid);

        return ApiResponse::success('Favorite status retrieved successfully.', [
            'favorited' => $isFavorited,
            'event_id' => $eventUuid,
        ]);
    }

    /**
     * Synchronize a list of favorite IDs from offline / guest storage.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'event_ids' => 'required_without:eventIds|array',
            'eventIds' => 'nullable|array',
        ]);

        $eventIds = $request->input('event_ids') ?? $request->input('eventIds') ?? [];
        $synced = $this->favoriteService->syncFavorites($request->user(), $eventIds);

        return ApiResponse::success('Favorites synchronized successfully.', [
            'synced' => $synced,
        ]);
    }
}
