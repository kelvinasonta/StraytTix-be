<?php

declare(strict_types=1);

namespace Modules\Events\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Events\app\Http\Requests\AssignEventStaffRequest;
use Modules\Events\app\Http\Requests\UpdateEventStaffRequest;
use Modules\Events\app\Services\EventStaffService;
use Modules\Events\app\Transformers\EventStaffResource;
use Modules\Organizer\app\Models\Organizer;

class EventStaffController extends Controller
{
    public function __construct(
        protected EventStaffService $eventStaffService
    ) {}

    /**
     * List all staff assigned to a specific event.
     */
    public function index(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $staff = $this->eventStaffService->getEventStaff($organizer, $eventUuid);

        return ApiResponse::success(
            'Event staff retrieved successfully.',
            EventStaffResource::collection($staff)
        );
    }

    /**
     * Assign staff to an event (single or bulk).
     */
    public function store(AssignEventStaffRequest $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $result = $this->eventStaffService->assignStaff(
            organizer: $organizer,
            eventUuid: $eventUuid,
            assigner: $request->user(),
            data: $request->validated()
        );

        $data = $result instanceof Collection
            ? EventStaffResource::collection($result)
            : new EventStaffResource($result);

        return ApiResponse::success(
            'Staff assigned to event successfully.',
            $data,
            201
        );
    }

    /**
     * Update an assigned staff member's role, permissions, or notes on an event.
     */
    public function update(
        UpdateEventStaffRequest $request,
        string $organizerUuid,
        string $eventUuid,
        string|int $userId
    ): JsonResponse {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $assignment = $this->eventStaffService->updateStaffAssignment(
            organizer: $organizer,
            eventUuid: $eventUuid,
            userId: $userId,
            actor: $request->user(),
            data: $request->validated()
        );

        return ApiResponse::success(
            'Event staff assignment updated successfully.',
            new EventStaffResource($assignment)
        );
    }

    /**
     * Remove a staff member from an event.
     */
    public function destroy(
        Request $request,
        string $organizerUuid,
        string $eventUuid,
        string|int $userId
    ): JsonResponse {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $this->eventStaffService->removeStaff(
            organizer: $organizer,
            eventUuid: $eventUuid,
            userId: $userId,
            actor: $request->user()
        );

        return ApiResponse::success('Staff member removed from event successfully.');
    }
}
