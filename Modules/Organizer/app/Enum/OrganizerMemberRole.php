<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Enum;

enum OrganizerMemberRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case FINANCE = 'finance';
    case MANAGER = 'manager';
    case STAFF = 'staff';

    public function level(): int
    {
        return match ($this) {
            self::OWNER => 5,
            self::ADMIN => 4,
            self::FINANCE => 3,
            self::MANAGER => 2,
            self::STAFF => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::ADMIN => 'Admin',
            self::FINANCE => 'Finance',
            self::MANAGER => 'Event Manager',
            self::STAFF => 'Event Staff',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OWNER => 'Full control over the organization, billing, team, and events.',
            self::ADMIN => 'Can manage organization settings, team members, finances, and all events.',
            self::FINANCE => 'Can view and manage orders, payouts, settlement bank accounts, and credits.',
            self::MANAGER => 'Can create and manage events, ticket types, event staff, and attendees.',
            self::STAFF => 'Can view events and check in attendees at the gate.',
        };
    }

    public function canManageRole(OrganizerMemberRole $targetRole): bool
    {
        if ($this === self::OWNER) {
            return true;
        }

        if ($this === self::ADMIN) {
            return $targetRole->level() < self::ADMIN->level();
        }

        if ($this === self::MANAGER) {
            return $targetRole->level() < self::MANAGER->level();
        }

        return false;
    }

    /**
     * Get default permissions granted to this role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::OWNER => [
                '*',
                ...OrganizerPermission::values(),
            ],
            self::ADMIN => [
                OrganizerPermission::ORG_VIEW->value,
                OrganizerPermission::ORG_UPDATE->value,
                OrganizerPermission::MEMBERS_VIEW->value,
                OrganizerPermission::MEMBERS_MANAGE->value,
                OrganizerPermission::FINANCE_VIEW->value,
                OrganizerPermission::FINANCE_MANAGE->value,
                OrganizerPermission::CREDITS_MANAGE->value,
                OrganizerPermission::VERIFICATION_VIEW->value,
                OrganizerPermission::VERIFICATION_MANAGE->value,
                OrganizerPermission::EVENTS_VIEW->value,
                OrganizerPermission::EVENTS_CREATE->value,
                OrganizerPermission::EVENTS_UPDATE->value,
                OrganizerPermission::EVENTS_DELETE->value,
                OrganizerPermission::ATTENDEES_VIEW->value,
                OrganizerPermission::ATTENDEES_MANAGE->value,
                OrganizerPermission::TICKETS_MANAGE->value,
                OrganizerPermission::AUDIT_LOGS_VIEW->value,
            ],
            self::FINANCE => [
                OrganizerPermission::ORG_VIEW->value,
                OrganizerPermission::VERIFICATION_VIEW->value,
                OrganizerPermission::MEMBERS_VIEW->value,
                OrganizerPermission::FINANCE_VIEW->value,
                OrganizerPermission::FINANCE_MANAGE->value,
                OrganizerPermission::CREDITS_MANAGE->value,
                OrganizerPermission::EVENTS_VIEW->value,
                OrganizerPermission::ATTENDEES_VIEW->value,
                OrganizerPermission::AUDIT_LOGS_VIEW->value,
            ],
            self::MANAGER => [
                OrganizerPermission::ORG_VIEW->value,
                OrganizerPermission::VERIFICATION_VIEW->value,
                OrganizerPermission::MEMBERS_VIEW->value,
                OrganizerPermission::FINANCE_VIEW->value,
                OrganizerPermission::CREDITS_MANAGE->value,
                OrganizerPermission::EVENTS_VIEW->value,
                OrganizerPermission::EVENTS_CREATE->value,
                OrganizerPermission::EVENTS_UPDATE->value,
                OrganizerPermission::ATTENDEES_VIEW->value,
                OrganizerPermission::ATTENDEES_MANAGE->value,
                OrganizerPermission::TICKETS_MANAGE->value,
            ],
            self::STAFF => [
                OrganizerPermission::ORG_VIEW->value,
                OrganizerPermission::VERIFICATION_VIEW->value,
                OrganizerPermission::MEMBERS_VIEW->value,
                OrganizerPermission::EVENTS_VIEW->value,
                OrganizerPermission::ATTENDEES_VIEW->value,
            ],
        };
    }

    /**
     * Check if this role has a specific permission.
     */
    public function hasPermission(OrganizerPermission|string $permission): bool
    {
        $permString = $permission instanceof OrganizerPermission ? $permission->value : $permission;
        $granted = $this->permissions();

        if (in_array('*', $granted, true)) {
            return true;
        }

        return in_array($permString, $granted, true);
    }

    /**
     * Get structured metadata for all roles.
     *
     * @return array<int, array{role: string, label: string, description: string, level: int, permissions: array<int, string>}>
     */
    public static function metadata(?self $requesterRole = null): array
    {
        return array_map(
            static fn (self $role): array => [
                'role' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
                'level' => $role->level(),
                'can_assign' => $requesterRole ? $requesterRole->canManageRole($role) && $role !== self::OWNER : false,
                'permissions' => $role->permissions(),
            ],
            self::cases()
        );
    }
}
