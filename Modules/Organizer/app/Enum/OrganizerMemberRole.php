<?php

namespace Modules\Organizer\app\Enum;

enum OrganizerMemberRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case STAFF = 'staff';

    public function level(): int
    {
        return match ($this) {
            self::OWNER => 4,
            self::ADMIN => 3,
            self::MANAGER => 2,
            self::STAFF => 1,
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
}
