<?php

declare(strict_types=1);

namespace Modules\Organizer\app\Enum;

enum OrganizerPermission: string
{
    case ORG_VIEW = 'org.view';
    case ORG_UPDATE = 'org.update';
    case ORG_DELETE = 'org.delete';

    case MEMBERS_VIEW = 'org.members.view';
    case MEMBERS_MANAGE = 'org.members.manage';

    case FINANCE_VIEW = 'org.finance.view';
    case FINANCE_MANAGE = 'org.finance.manage';
    case CREDITS_MANAGE = 'org.credits.manage';

    case VERIFICATION_VIEW = 'org.verification.view';
    case VERIFICATION_MANAGE = 'org.verification.manage';

    case EVENTS_VIEW = 'org.events.view';
    case EVENTS_CREATE = 'org.events.create';
    case EVENTS_UPDATE = 'org.events.update';
    case EVENTS_DELETE = 'org.events.delete';

    case ATTENDEES_VIEW = 'org.attendees.view';
    case ATTENDEES_MANAGE = 'org.attendees.manage';

    case TICKETS_MANAGE = 'org.tickets.manage';
    case AUDIT_LOGS_VIEW = 'org.audit_logs.view';

    /**
     * Get all permission string values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases()
        );
    }
}
