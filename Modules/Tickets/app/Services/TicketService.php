<?php

namespace Modules\Tickets\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Tickets\app\Models\Ticket;
use Modules\Tickets\app\Models\TicketType;

class TicketService
{
    /**
     * Retrieve ticket types for an event (by UUID, ID, or slug).
     */
    public function getEventTicketTypes(string $eventIdentifier, bool $onlyAvailable = false): Collection
    {
        $event = $this->resolveEvent($eventIdentifier);

        $query = $event->ticketTypes()->latest();

        if ($onlyAvailable) {
            $query->where('status', 'active');
        }

        $ticketTypes = $query->get();

        if ($onlyAvailable) {
            return $ticketTypes->filter(fn (TicketType $tt) => $tt->isAvailable())->values();
        }

        return $ticketTypes;
    }

    /**
     * Create a ticket type for an event.
     */
    public function createTicketType(string $eventIdentifier, array $data): TicketType
    {
        $event = $this->resolveEvent($eventIdentifier);

        return TicketType::create(array_merge($data, [
            'event_id' => $event->id,
            'status' => $data['status'] ?? 'active',
            'currency' => $data['currency'] ?? 'NGN',
            'price' => $data['type'] === 'free' ? 0.00 : ($data['price'] ?? 0.00),
        ]));
    }

    /**
     * Update an existing ticket type.
     */
    public function updateTicketType(string $ticketTypeUuid, array $data): TicketType
    {
        $ticketType = TicketType::where('uuid', $ticketTypeUuid)->firstOrFail();

        if (isset($data['type']) && $data['type'] === 'free') {
            $data['price'] = 0.00;
        }

        $ticketType->update($data);

        return $ticketType->fresh();
    }

    /**
     * Delete a ticket type.
     */
    public function deleteTicketType(string $ticketTypeUuid): bool
    {
        $ticketType = TicketType::where('uuid', $ticketTypeUuid)->firstOrFail();

        $event = $ticketType->event;
        if ($event) {
            $statusValue = is_object($event->status) ? ($event->status->value ?? '') : (string) $event->status;
            if (strtolower($statusValue) === 'published') {
                $remainingCount = TicketType::where('event_id', $event->id)->count();
                if ($remainingCount <= 1) {
                    throw new \DomainException('Cannot delete the last remaining ticket for a published event. Please unpublish the event or add another ticket tier first.');
                }
            }
        }

        return (bool) $ticketType->delete();
    }

    /**
     * Get tickets owned by the authenticated user.
     */
    public function getUserTickets(User $user, array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Ticket::with(['event.organizer', 'event.location', 'ticketType'])
            ->where('user_id', $user->id)
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['perPage'] ?? $filters['per_page'] ?? null;

        if ($perPage) {
            return $query->paginate((int) $perPage);
        }

        return $query->get();
    }

    /**
     * Get a specific ticket for the user.
     */
    public function getUserTicket(User $user, string $ticketUuid): Ticket
    {
        return Ticket::with(['event.organizer', 'event.location', 'ticketType'])
            ->where('user_id', $user->id)
            ->where('uuid', $ticketUuid)
            ->firstOrFail();
    }

    /**
     * Issue a ticket to a user for an event and ticket type.
     */
    public function issueTicket(User $user, string $ticketTypeUuid): Ticket
    {
        $ticketType = TicketType::with('event')->where('uuid', $ticketTypeUuid)->firstOrFail();

        if (! $ticketType->isAvailable()) {
            throw new \DomainException('This ticket type is currently not available for purchase or RSVP.');
        }

        $ticket = Ticket::create([
            'event_id' => $ticketType->event_id,
            'user_id' => $user->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'TKT-'.strtoupper(Str::random(8)),
            'price_paid' => $ticketType->price,
            'currency' => $ticketType->currency,
            'status' => 'valid',
            'qr_code' => 'EVT-'.$ticketType->event->uuid.'-TKT-'.Str::uuid(),
        ]);

        $ticketType->increment('sold_quantity');

        return $ticket->load(['event.organizer', 'event.location', 'ticketType', 'user']);
    }

    /**
     * Helper to resolve an event by UUID, numeric ID, or slug.
     */
    protected function resolveEvent(string $eventIdentifier): Event
    {
        $event = is_numeric($eventIdentifier)
            ? Event::find($eventIdentifier)
            : Event::where('uuid', $eventIdentifier)->orWhere('slug', $eventIdentifier)->first();

        if (! $event) {
            throw new ModelNotFoundException('Event not found.');
        }

        return $event;
    }
}
