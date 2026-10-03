<?php

declare(strict_types=1);

namespace Modules\Events\app\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Audit\app\Services\AuditService;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventStaff;
use Modules\Organizer\app\Models\Organizer;
use Modules\Organizer\app\Models\OrganizerMember;

class EventStaffService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get all staff assigned to a specific event.
     *
     * @return Collection<int, EventStaff>
     */
    public function getEventStaff(Organizer $organizer, string $eventUuid): Collection
    {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);

        return EventStaff::with(['user', 'assigner'])
            ->where('event_id', $event->id)
            ->latest()
            ->get();
    }

    /**
     * Assign staff to an event (supports single assignment or bulk array).
     *
     * @param  array<string, mixed>  $data
     * @return SupportCollection<int, EventStaff>|EventStaff
     */
    public function assignStaff(
        Organizer $organizer,
        string $eventUuid,
        User $assigner,
        array $data
    ): SupportCollection|EventStaff {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);

        $isBulk = ! isset($data['user_id']) && isset($data['staff']) && is_array($data['staff']);
        $items = $isBulk ? $data['staff'] : [$data];

        $assignedRecords = [];

        DB::transaction(function () use (&$assignedRecords, $items, $event, $organizer, $assigner) {
            foreach ($items as $item) {
                $userIdentifier = $item['user_id'] ?? null;
                if (! $userIdentifier) {
                    throw ValidationException::withMessages([
                        'user_id' => ['User ID is required for each staff assignment.'],
                    ]);
                }

                $user = User::where('id', $userIdentifier)
                    ->orWhere('uuid', (string) $userIdentifier)
                    ->first();

                if (! $user) {
                    throw ValidationException::withMessages([
                        'user_id' => ["User with identifier '{$userIdentifier}' does not exist."],
                    ]);
                }

                $isOwner = (int) $organizer->user_id === (int) $user->id;
                $member = OrganizerMember::where('organizer_id', $organizer->id)
                    ->where('user_id', $user->id)
                    ->first();

                if (! $isOwner && ! $member) {
                    throw ValidationException::withMessages([
                        'user_id' => ["User '{$user->name}' is not a team member of this organizer."],
                    ]);
                }

                $staffRecord = EventStaff::updateOrCreate(
                    [
                        'event_id' => $event->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'organizer_member_id' => $member?->id,
                        'assigned_by' => $assigner->id,
                        'role' => $item['role'] ?? 'staff',
                        'permissions' => $item['permissions'] ?? [],
                        'notes' => $item['notes'] ?? null,
                    ]
                );

                $assignedRecords[] = $staffRecord->fresh(['user', 'assigner']);
            }
        });

        $this->auditService->log(
            action: 'event.staff_assigned',
            description: 'Assigned '.count($assignedRecords)." staff member(s) to event '{$event->title}'",
            organizer: $organizer,
            user: $assigner,
            metadata: [
                'event_id' => $event->id,
                'event_uuid' => $event->uuid,
                'assigned_user_ids' => collect($assignedRecords)->pluck('user_id')->all(),
            ]
        );

        return $isBulk ? collect($assignedRecords) : $assignedRecords[0];
    }

    /**
     * Update an existing event staff assignment.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateStaffAssignment(
        Organizer $organizer,
        string $eventUuid,
        int|string $userId,
        User $actor,
        array $data
    ): EventStaff {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);
        $user = $this->resolveUser($userId);

        $assignment = EventStaff::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $updateData = [];
        if (array_key_exists('role', $data)) {
            $updateData['role'] = $data['role'];
        }
        if (array_key_exists('permissions', $data)) {
            $updateData['permissions'] = $data['permissions'];
        }
        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = $data['notes'];
        }

        $assignment->update($updateData);

        $this->auditService->log(
            action: 'event.staff_updated',
            description: "Updated assignment for staff member '{$user->name}' on event '{$event->title}'",
            organizer: $organizer,
            user: $actor,
            metadata: [
                'event_id' => $event->id,
                'event_uuid' => $event->uuid,
                'staff_user_id' => $user->id,
                'updated_fields' => array_keys($updateData),
            ]
        );

        return $assignment->fresh(['user', 'assigner']);
    }

    /**
     * Remove a staff member from an event.
     */
    public function removeStaff(
        Organizer $organizer,
        string $eventUuid,
        int|string $userId,
        User $actor
    ): bool {
        $event = $this->getOrganizerEvent($organizer, $eventUuid);
        $user = $this->resolveUser($userId);

        $assignment = EventStaff::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $assignment->delete();

        $this->auditService->log(
            action: 'event.staff_removed',
            description: "Removed staff member '{$user->name}' from event '{$event->title}'",
            organizer: $organizer,
            user: $actor,
            metadata: [
                'event_id' => $event->id,
                'event_uuid' => $event->uuid,
                'removed_user_id' => $user->id,
            ]
        );

        return true;
    }

    /**
     * Find organizer's event by UUID.
     */
    private function getOrganizerEvent(Organizer $organizer, string $eventUuid): Event
    {
        return Event::where('organizer_id', $organizer->id)
            ->where('uuid', $eventUuid)
            ->firstOrFail();
    }

    /**
     * Resolve a User by numeric ID or UUID.
     */
    private function resolveUser(int|string $userIdentifier): User
    {
        return User::where('id', $userIdentifier)
            ->orWhere('uuid', (string) $userIdentifier)
            ->firstOrFail();
    }
}
