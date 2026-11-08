<?php

namespace Modules\Tickets\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Events\app\Models\Event;
use Modules\Tickets\app\Models\TicketType;
use Modules\Tickets\app\Services\TicketService;
use Modules\Tickets\app\Transformers\TicketTypeResource;

class TicketTypesController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * List all ticket types for an event.
     */
    public function index(Request $request, string $eventUuid): JsonResponse
    {
        $onlyAvailable = $request->boolean('available', false);
        $ticketTypes = $this->ticketService->getEventTicketTypes($eventUuid, $onlyAvailable);

        return ApiResponse::success(
            'Ticket types retrieved successfully.',
            TicketTypeResource::collection($ticketTypes)
        );
    }

    /**
     * Store a new ticket type for an event.
     */
    public function store(Request $request, string $eventUuid): JsonResponse
    {
        $event = Event::with(['organizer.members'])->where('uuid', $eventUuid)->firstOrFail();
        if (! Gate::forUser($request->user())->allows('update', $event)) {
            return ApiResponse::error('Unauthorized to manage ticket types for this event.', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:free,paid',
            'ticket_type' => 'nullable|in:single,group',
            'group_size' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'ticket_capacity' => 'nullable|in:limited,unlimited',
            'quantity' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'sale_starts_at' => 'nullable|date',
            'sale_ends_at' => 'nullable|date|after_or_equal:sale_starts_at',
            'status' => 'nullable|in:active,paused,draft',
        ]);

        $ticketType = $this->ticketService->createTicketType($eventUuid, $validated);

        return ApiResponse::success(
            'Ticket type created successfully.',
            new TicketTypeResource($ticketType),
            201
        );
    }

    /**
     * Update an existing ticket type.
     */
    public function update(Request $request, string $ticketTypeUuid): JsonResponse
    {
        $ticketType = TicketType::with(['event.organizer.members'])->where('uuid', $ticketTypeUuid)->firstOrFail();
        if (! Gate::forUser($request->user())->allows('update', $ticketType->event)) {
            return ApiResponse::error('Unauthorized to update this ticket type.', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:free,paid',
            'ticket_type' => 'nullable|in:single,group',
            'group_size' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'ticket_capacity' => 'nullable|in:limited,unlimited',
            'quantity' => 'nullable|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'sale_starts_at' => 'nullable|date',
            'sale_ends_at' => 'nullable|date',
            'status' => 'nullable|in:active,paused,draft,sold_out',
        ]);

        $ticketType = $this->ticketService->updateTicketType($ticketTypeUuid, $validated);

        return ApiResponse::success(
            'Ticket type updated successfully.',
            new TicketTypeResource($ticketType)
        );
    }

    /**
     * Delete a ticket type.
     */
    public function destroy(Request $request, string $ticketTypeUuid): JsonResponse
    {
        $ticketType = TicketType::with(['event.organizer.members'])->where('uuid', $ticketTypeUuid)->firstOrFail();
        if (! Gate::forUser($request->user())->allows('update', $ticketType->event)) {
            return ApiResponse::error('Unauthorized to delete this ticket type.', 403);
        }

        try {
            $this->ticketService->deleteTicketType($ticketTypeUuid);

            return ApiResponse::success('Ticket type deleted successfully.');
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
