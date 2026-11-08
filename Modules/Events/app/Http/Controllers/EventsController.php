<?php

namespace Modules\Events\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Events\app\DTOs\EventCustomQuestionDTO;
use Modules\Events\app\DTOs\EventDTO;
use Modules\Events\app\DTOs\EventLineupDTO;
use Modules\Events\app\DTOs\EventLocationDTO;
use Modules\Events\app\DTOs\EventPromoterDTO;
use Modules\Events\app\DTOs\EventScheduleDTO;
use Modules\Events\app\DTOs\EventScheduleItemDTO;
use Modules\Events\app\DTOs\EventSettingDTO;
use Modules\Events\app\Http\Requests\StoreEventRequest;
use Modules\Events\app\Http\Requests\UpdateEventRequest;
use Modules\Events\app\Models\EventStaff;
use Modules\Events\app\Services\EventService;
use Modules\Events\app\Transformers\EventResource;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Models\Organizer;

class EventsController extends Controller
{
    public function __construct(
        protected EventService $eventService
    ) {}

    public function index(Request $request, string $organizerUuid): JsonResponse
    {
        $organizer = $request->attributes->get('active_organizer');
        $requesterRole = $request->attributes->get('active_organizer_role');

        $filters = $request->only([
            'status',
            'eventType',
            'search',
            'perPage',
        ]);

        $isStaff = ($requesterRole instanceof OrganizerMemberRole && $requesterRole === OrganizerMemberRole::STAFF)
            || (is_string($requesterRole) && strtolower($requesterRole) === 'staff');

        if ($request->boolean('assigned_only')) {
            $filters['assigned_user_id'] = $request->user()->id;
        } elseif ($isStaff) {
            $hasSpecificAssignments = EventStaff::where('user_id', $request->user()->id)
                ->whereHas('event', fn ($q) => $q->where('organizer_id', $organizer->id))
                ->exists();

            if ($hasSpecificAssignments) {
                $filters['assigned_user_id'] = $request->user()->id;
            }
        }

        $events = $this->eventService->getOrganizerEvents($organizer, $filters);

        return ApiResponse::success(
            'Events retrieved successfully.',
            EventResource::collection($events)->response()->getData(true)
        );
    }

    /**
     * Store a newly created event for an organizer.
     */
    public function store(StoreEventRequest $request, string $organizerUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $validated = $request->validated();

        $eventDto = EventDTO::fromArray($validated);
        $eventLocationDto = EventLocationDTO::fromArray($validated);
        $scheduleDto = EventScheduleDTO::fromArray($validated);

        $scheduleItemDto = array_map(
            fn (array $schedule) => EventScheduleItemDTO::fromArray($schedule),
            $validated['schedules'] ?? []
        );

        $lineupDto = array_map(
            fn (array $lineup) => EventLineupDTO::fromArray($lineup),
            $validated['lineup'] ?? []
        );

        $settingDto = EventSettingDTO::fromArray($validated);

        $customQuestionDtos = array_map(
            fn (array $question) => EventCustomQuestionDTO::fromArray($question),
            $validated['customQuestions'] ?? []
        );

        $promoterDtos = array_map(
            fn (array $promoter) => EventPromoterDTO::fromArray($promoter),
            $validated['promoters'] ?? []
        );

        $event = $this->eventService->createEvent(
            $organizer,
            $request->user(),
            $eventDto,
            $scheduleDto,
            $eventLocationDto,
            $scheduleItemDto,
            $lineupDto,
            $settingDto,
            $customQuestionDtos,
            $promoterDtos
        );

        return ApiResponse::success(
            'Event created successfully.',
            new EventResource($event),
            201
        );
    }

    /**
     * Show the specified organizer event.
     */
    public function show(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $event = $this->eventService->getOrganizerEvent($organizer, $eventUuid);

        return ApiResponse::success(
            'Event details retrieved.',
            new EventResource($event)
        );
    }

    /**
     * Update the specified organizer event.
     */
    public function update(UpdateEventRequest $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $event = $this->eventService->updateEvent(
            $organizer,
            $request->user(),
            $eventUuid,
            $request->validated()
        );

        return ApiResponse::success(
            'Event updated successfully.',
            new EventResource($event)
        );
    }

    /**
     * Remove the specified organizer event.
     */
    public function destroy(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $this->eventService->deleteEvent($organizer, $request->user(), $eventUuid);

        return ApiResponse::success('Event deleted successfully.');
    }

    /**
     * Publish an organizer event.
     */
    public function publish(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $event = $this->eventService->publishEvent($organizer, $request->user(), $eventUuid);

        return ApiResponse::success(
            'Event published successfully.',
            new EventResource($event)
        );
    }

    public function unpublish(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $event = $this->eventService->unpublishEvent($organizer, $request->user(), $eventUuid);

        return ApiResponse::success(
            'Event unpublished successfully.',
            new EventResource($event)
        );
    }

    public function duplicate(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $event = $this->eventService->duplicateEvent($organizer, $request->user(), $eventUuid);

        return ApiResponse::success(
            'Event duplicated successfully.',
            new EventResource($event),
            201
        );
    }

    public function stats(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $stats = $this->eventService->getEventStats($organizer, $eventUuid);

        return ApiResponse::success(
            'Event statistics retrieved successfully.',
            $stats
        );
    }

    public function archive(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $event = $this->eventService->archiveEvent($organizer, $request->user(), $eventUuid);

        return ApiResponse::success(
            'Event archived successfully.',
            new EventResource($event)
        );
    }

    public function cancel(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        /** @var Organizer $organizer */
        $organizer = $request->attributes->get('active_organizer');

        $event = $this->eventService->cancelEvent($organizer, $request->user(), $eventUuid);

        return ApiResponse::success(
            'Event cancelled successfully.',
            new EventResource($event)
        );
    }

    /**
     * Public explore events endpoint.
     */
    public function explore(Request $request): JsonResponse
    {
        $events = $this->eventService->getPublicEvents($request->only([
            'search',
            'eventType',
            'category',
            'organizerUuid',
            'perPage',
        ]));

        return ApiResponse::success(
            'Public events retrieved.',
            EventResource::collection($events)->response()->getData(true)
        );
    }

    /**
     * Public single event details by slug.
     */
    public function showPublic(string $slug): JsonResponse
    {
        $event = $this->eventService->getPublicEventBySlug($slug);

        return ApiResponse::success(
            'Public event retrieved.',
            new EventResource($event)
        );
    }
}
