<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Policies;

use Modules\Auth\app\Models\User;
use Modules\Organizer\app\Enum\OrganizerMemberRole;
use Modules\Organizer\app\Enum\OrganizerPermission;
use Modules\Organizer\app\Models\Organizer;

class OrganizerPolicy
{
    /**
     * Resolve the member role of the given user in the organization.
     */
    public function getMemberRole(User $user, Organizer $organizer): ?OrganizerMemberRole
    {
        return $organizer->getRoleForUser($user);
    }

    /**
     * Determine whether the user can view the organization management profile.
     */
    public function view(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getMemberRole($user, $organizer);

        return $role !== null;
    }

    /**
     * Determine whether the user can update the organization details.
     */
    public function update(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getMemberRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::ORG_UPDATE) ?? false;
    }

    /**
     * Determine whether the user can delete the organization.
     */
    public function delete(User $user, Organizer $organizer): bool
    {
        $role = $this->getMemberRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::ORG_DELETE) ?? false;
    }

    /**
     * Determine whether the user can manage team members and invitations.
     */
    public function manageMembers(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getMemberRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::MEMBERS_MANAGE) ?? false;
    }

    /**
     * Determine whether the user can view team members.
     */
    public function viewMembers(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getMemberRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::MEMBERS_VIEW) ?? false;
    }

    /**
     * Determine whether the user can manage financial settings, bank accounts, and payouts.
     */
    public function manageFinances(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getMemberRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::FINANCE_MANAGE) ?? false;
    }

    /**
     * Determine whether the user can view financial statements and reports.
     */
    public function viewFinances(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getMemberRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::FINANCE_VIEW) ?? false;
    }

    /**
     * Determine whether the user can manage credit balances and wallet top-ups.
     */
    public function manageCredits(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getMemberRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::CREDITS_MANAGE) ?? false;
    }

    /**
     * Determine whether the user can submit and manage organization KYC verifications.
     */
    public function manageVerification(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getMemberRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::VERIFICATION_MANAGE) ?? false;
    }

    /**
     * Determine whether the user can view organization audit logs.
     */
    public function viewAuditLogs(User $user, Organizer $organizer): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }

        $role = $this->getMemberRole($user, $organizer);

        return $role?->hasPermission(OrganizerPermission::AUDIT_LOGS_VIEW) ?? false;
    }
}
