<?php

declare(strict_types=1);

namespace Modules\Tickets\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;
use Modules\Tickets\app\Exceptions\TicketCheckInException;
use Modules\Tickets\app\Models\Ticket;

class AttendeeService
{
    /**
     * Get paginated attendee tickets for an event.
     */
    public function getEventAttendees(Organizer $organizer, string $eventUuid, array $filters = []): LengthAwarePaginator
    {
        $event = Event::where('uuid', $eventUuid)->where('organizer_id', $organizer->id)->firstOrFail();

        $query = Ticket::with(['ticketType', 'user', 'order'])
            ->where('event_id', $event->id)
            ->latest();

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', $search)
                    ->orWhere('attendee_name', 'like', $search)
                    ->orWhere('attendee_email', 'like', $search);
            });
        }

        if (isset($filters['checked_in']) && $filters['checked_in'] !== '') {
            $checkedIn = filter_var($filters['checked_in'], FILTER_VALIDATE_BOOLEAN);
            if ($checkedIn) {
                $query->whereNotNull('checked_in_at');
            } else {
                $query->whereNull('checked_in_at');
            }
        }

        if (! empty($filters['ticket_type_uuid'])) {
            $query->whereHas('ticketType', function ($q) use ($filters) {
                $q->where('uuid', $filters['ticket_type_uuid']);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? $filters['perPage'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * Export attendees as CSV.
     */
    public function exportAttendeesCsv(Organizer $organizer, string $eventUuid): string
    {
        $event = Event::where('uuid', $eventUuid)->where('organizer_id', $organizer->id)->firstOrFail();

        $tickets = Ticket::with(['ticketType', 'order'])
            ->where('event_id', $event->id)
            ->latest()
            ->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'Ticket Number',
            'Attendee Name',
            'Attendee Email',
            'Ticket Tier',
            'Price Paid',
            'Currency',
            'Status',
            'Checked In',
            'Checked In At',
            'Order Number',
        ]);

        foreach ($tickets as $ticket) {
            fputcsv($output, [
                $ticket->ticket_number,
                $ticket->attendee_name ?: 'N/A',
                $ticket->attendee_email ?: 'N/A',
                $ticket->ticketType ? $ticket->ticketType->name : 'General',
                $ticket->price_paid,
                $ticket->currency,
                $ticket->status,
                $ticket->checked_in_at ? 'Yes' : 'No',
                $ticket->checked_in_at ? $ticket->checked_in_at->toIso8601String() : '',
                $ticket->order ? $ticket->order->order_number : 'N/A',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }

    /**
     * Check in an attendee using ticket number or QR code string.
     */
    public function checkInAttendee(Organizer $organizer, string $eventUuid, string $identifier): Ticket
    {
        $event = Event::where('uuid', $eventUuid)->where('organizer_id', $organizer->id)->firstOrFail();

        $ticket = Ticket::with(['ticketType', 'user', 'order'])
            ->where('event_id', $event->id)
            ->where(function ($q) use ($identifier) {
                $q->where('ticket_number', $identifier)
                    ->orWhere('qr_code', $identifier);
            })
            ->first();

        if (! $ticket) {
            throw new TicketCheckInException('Invalid ticket for this event.');
        }

        if (in_array(strtolower($ticket->status), ['cancelled', 'refunded', 'void'])) {
            throw new TicketCheckInException("Ticket is invalid or {$ticket->status}.");
        }

        if ($ticket->checked_in_at !== null) {
            $formattedTime = $ticket->checked_in_at->format('M d, Y h:i A');
            throw new TicketCheckInException("Ticket was already checked in at {$formattedTime}.");
        }

        $ticket->update([
            'checked_in_at' => now(),
            'status' => 'used',
        ]);

        return $ticket->fresh(['ticketType', 'user', 'order']);
    }

    /**
     * Undo check-in for an attendee using ticket number, QR code, or ticket uuid.
     */
    public function undoCheckInAttendee(Organizer $organizer, string $eventUuid, string $identifier): Ticket
    {
        $event = Event::where('uuid', $eventUuid)->where('organizer_id', $organizer->id)->firstOrFail();

        $ticket = Ticket::with(['ticketType', 'user', 'order'])
            ->where('event_id', $event->id)
            ->where(function ($q) use ($identifier) {
                $q->where('ticket_number', $identifier)
                    ->orWhere('qr_code', $identifier)
                    ->orWhere('uuid', $identifier);
            })
            ->first();

        if (! $ticket) {
            throw new TicketCheckInException('Invalid ticket for this event.');
        }

        if (in_array(strtolower($ticket->status), ['cancelled', 'refunded', 'void'])) {
            throw new TicketCheckInException("Ticket is invalid or {$ticket->status}.");
        }

        if ($ticket->checked_in_at === null) {
            throw new TicketCheckInException('Ticket is not currently checked in.');
        }

        $ticket->update([
            'checked_in_at' => null,
            'status' => 'valid',
        ]);

        return $ticket->fresh(['ticketType', 'user', 'order']);
    }

    /**
     * Get live gate check-in statistics for an event.
     *
     * @return array{
     *     total_tickets: int,
     *     checked_in_count: int,
     *     pending_count: int,
     *     check_in_rate: float,
     *     recent_check_ins: array<int, array{
     *         ticket_number: string,
     *         attendee_name: string|null,
     *         attendee_email: string|null,
     *         ticket_tier: string,
     *         checked_in_at: string|null
     *     }>
     * }
     */
    public function getEventGateStats(Organizer $organizer, string $eventUuid): array
    {
        $event = Event::where('uuid', $eventUuid)->where('organizer_id', $organizer->id)->firstOrFail();

        $query = Ticket::where('event_id', $event->id);

        $totalTickets = (clone $query)->count();
        $checkedInCount = (clone $query)->whereNotNull('checked_in_at')->count();
        $pendingCount = (clone $query)->whereNull('checked_in_at')->count();

        $checkInRate = $totalTickets > 0
            ? round(($checkedInCount / $totalTickets) * 100, 2)
            : 0.0;

        $recentCheckIns = Ticket::with('ticketType')
            ->where('event_id', $event->id)
            ->whereNotNull('checked_in_at')
            ->orderByDesc('checked_in_at')
            ->limit(5)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'ticket_number' => $ticket->ticket_number,
                'attendee_name' => $ticket->attendee_name,
                'attendee_email' => $ticket->attendee_email,
                'ticket_tier' => $ticket->ticketType?->name ?? 'General',
                'checked_in_at' => $ticket->checked_in_at?->toISOString(),
            ])
            ->values()
            ->all();

        return [
            'total_tickets' => $totalTickets,
            'checked_in_count' => $checkedInCount,
            'pending_count' => $pendingCount,
            'check_in_rate' => $checkInRate,
            'recent_check_ins' => $recentCheckIns,
        ];
    }
}
