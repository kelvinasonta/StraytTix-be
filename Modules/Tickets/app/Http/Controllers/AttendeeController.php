<?php

declare(strict_types=1);

namespace Modules\Tickets\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Organizer\app\Models\Organizer;
use Modules\Tickets\app\Exceptions\TicketCheckInException;
use Modules\Tickets\app\Services\AttendeeService;
use Modules\Tickets\app\Transformers\TicketResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendeeController extends Controller
{
    public function __construct(
        protected AttendeeService $attendeeService
    ) {}

    /**
     * List attendees/tickets for an event.
     */
    public function index(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        $organizer = $request->attributes->get('active_organizer')
            ?? Organizer::where('uuid', $organizerUuid)->firstOrFail();

        $attendees = $this->attendeeService->getEventAttendees($organizer, $eventUuid, $request->all());

        return ApiResponse::success(
            'Attendees retrieved successfully.',
            TicketResource::collection($attendees)->response()->getData(true)
        );
    }

    /**
     * Export attendees as CSV.
     */
    public function export(Request $request, string $organizerUuid, string $eventUuid): StreamedResponse
    {
        $organizer = $request->attributes->get('active_organizer')
            ?? Organizer::where('uuid', $organizerUuid)->firstOrFail();

        $csv = $this->attendeeService->exportAttendeesCsv($organizer, $eventUuid);

        $filename = "attendees-{$eventUuid}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Scan and check in an attendee ticket via ticket number or QR code.
     */
    public function checkIn(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
        ]);

        $organizer = $request->attributes->get('active_organizer')
            ?? Organizer::where('uuid', $organizerUuid)->firstOrFail();

        try {
            $ticket = $this->attendeeService->checkInAttendee($organizer, $eventUuid, $validated['identifier']);

            return ApiResponse::success(
                'Attendee checked in successfully.',
                new TicketResource($ticket)
            );
        } catch (TicketCheckInException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Undo check-in via request body identifier (ticket number, QR code, or UUID).
     */
    public function undoCheckIn(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
        ]);

        $organizer = $request->attributes->get('active_organizer')
            ?? Organizer::where('uuid', $organizerUuid)->firstOrFail();

        try {
            $ticket = $this->attendeeService->undoCheckInAttendee($organizer, $eventUuid, $validated['identifier']);

            return ApiResponse::success(
                'Attendee check-in reversed successfully.',
                new TicketResource($ticket)
            );
        } catch (TicketCheckInException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Undo check-in via ticket UUID route parameter.
     */
    public function undoCheckInByUuid(Request $request, string $organizerUuid, string $eventUuid, string $ticketUuid): JsonResponse
    {
        $organizer = $request->attributes->get('active_organizer')
            ?? Organizer::where('uuid', $organizerUuid)->firstOrFail();

        try {
            $ticket = $this->attendeeService->undoCheckInAttendee($organizer, $eventUuid, $ticketUuid);

            return ApiResponse::success(
                'Attendee check-in reversed successfully.',
                new TicketResource($ticket)
            );
        } catch (TicketCheckInException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Get live gate statistics for event check-in.
     */
    public function stats(Request $request, string $organizerUuid, string $eventUuid): JsonResponse
    {
        $organizer = $request->attributes->get('active_organizer')
            ?? Organizer::where('uuid', $organizerUuid)->firstOrFail();

        $stats = $this->attendeeService->getEventGateStats($organizer, $eventUuid);

        return ApiResponse::success(
            'Gate statistics retrieved successfully.',
            $stats
        );
    }
}
