<?php

declare(strict_types=1);

namespace Modules\Events\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventCollaborator;
use Modules\Events\app\Models\EventStaff;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerPermission;
use Modules\Organizer\app\Models\Organizer;

class EventPolicy
{
    /**
     * Resolve the organizer model safely without triggering lazy-loading violations.
     */
    protected function resolveOrganizer(Event $event): ?Organizer
    {
        if ($event->relationLoaded('organizer')) {
            return $event->organizer;
        }

        return Organizer::find($event->organizer_id);
    }

    /**
     * Resolve the organizer member role for this user.
     */
    protected function getOrganizerRole(User $user, ?Organizer $organizer): ?OrganizerMemberRole
    {
        return $organizer?->getRoleForUser($user);
    }

    /**
     * Resolve the event collaborator record for this user.
     */
    protected function getCollaborator(User $user, Event $event): ?EventCollaborator
    {
        return EventCollaborator::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Resolve the event staff assignment for this user.
     */
    protected function getEventStaff(User $user, Event $event): ?EventStaff
    {
        return EventStaff::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Determine whether the user can create events for the organization.
     */
    public function create(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getOrganizerRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::EVENTS_CREATE) ?? false;
    }

    /**
     * Determine whether the user can view event management details.
     */
    public function view(User $user, Event $event): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getOrganizerRole($user, $this->resolveOrganizer($event));
        if ($role?->hasPermission(OrganizerPermission::EVENTS_VIEW)) {
            return true;
        }

        $collaborator = $this->getCollaborator($user, $event);
        if ($collaborator?->hasCapability('event.view')) {
            return true;
        }

        return $this->getEventStaff($user, $event) !== null;
    }

    /**
     * Determine whether the user can update the event.
     */
    public function update(User $user, Event $event): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getOrganizerRole($user, $this->resolveOrganizer($event));
        if ($role?->hasPermission(OrganizerPermission::EVENTS_UPDATE)) {
            return true;
        }

        $collaborator = $this->getCollaborator($user, $event);
        if ($collaborator?->hasCapability('event.update')) {
            return true;
        }

        $staff = $this->getEventStaff($user, $event);
        if ($staff) {
            $perms = $staff->permissions ?? [];

            return in_array('event.update', $perms, true) || in_array('manage_event', $perms, true);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getOrganizerRole($user, $this->resolveOrganizer($event));

        return $role?->hasPermission(OrganizerPermission::EVENTS_DELETE) ?? false;
    }

    /**
     * Determine whether the user can view the attendee list.
     */
    public function viewAttendees(User $user, Event $event): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getOrganizerRole($user, $this->resolveOrganizer($event));
        if ($role?->hasPermission(OrganizerPermission::ATTENDEES_VIEW)) {
            return true;
        }

        $collaborator = $this->getCollaborator($user, $event);
        if ($collaborator?->hasCapability('event.attendees.view')) {
            return true;
        }

        $staff = $this->getEventStaff($user, $event);
        if ($staff) {
            $perms = $staff->permissions ?? [];

            return in_array('view_attendees', $perms, true)
                || in_array('event.attendees.view', $perms, true)
                || in_array($staff->role, ['manager', 'admin', 'co_host'], true);
        }

        return false;
    }

    /**
     * Determine whether the user can scan and validate tickets for this event.
     */
    public function scanTickets(User $user, Event $event): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getOrganizerRole($user, $this->resolveOrganizer($event));
        if ($role?->hasPermission(OrganizerPermission::ATTENDEES_VIEW)) {
            return true;
        }

        $collaborator = $this->getCollaborator($user, $event);
        if ($collaborator?->hasCapability('event.tickets.scan')) {
            return true;
        }

        $staff = $this->getEventStaff($user, $event);
        if ($staff) {
            $perms = $staff->permissions ?? [];

            return in_array('scan_tickets', $perms, true)
                || in_array('event.tickets.scan', $perms, true)
                || in_array($staff->role, ['scanner', 'check_in_agent', 'manager', 'staff'], true);
        }

        return false;
    }
}
