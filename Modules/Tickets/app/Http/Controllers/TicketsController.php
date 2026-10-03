<?php

namespace Modules\Tickets\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tickets\app\Services\TicketService;
use Modules\Tickets\app\Transformers\TicketResource;

class TicketsController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    /**
     * Display a listing of tickets owned by the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $tickets = $this->ticketService->getUserTickets($request->user(), $request->only([
            'status',
            'perPage',
            'per_page',
        ]));

        $data = $tickets instanceof LengthAwarePaginator
            ? TicketResource::collection($tickets)->response()->getData(true)
            : TicketResource::collection($tickets);

        return ApiResponse::success('Tickets retrieved successfully.', $data);
    }

    /**
     * Show the specified ticket.
     */
    public function show(Request $request, string $ticketUuid): JsonResponse
    {
        $ticket = $this->ticketService->getUserTicket($request->user(), $ticketUuid);

        return ApiResponse::success(
            'Ticket details retrieved successfully.',
            new TicketResource($ticket)
        );
    }

    /**
     * Reserve / Issue a ticket for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'ticket_type_id' => 'required_without:ticket_type_uuid|string',
            'ticket_type_uuid' => 'nullable|string',
        ]);

        $ticketTypeUuid = $request->input('ticket_type_uuid') ?? $request->input('ticket_type_id');

        try {
            $ticket = $this->ticketService->issueTicket($request->user(), $ticketTypeUuid);

            return ApiResponse::success(
                'Ticket issued successfully.',
                new TicketResource($ticket),
                201
            );
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 422, 'TICKET_UNAVAILABLE');
        }
    }
}
