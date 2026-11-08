<?php

declare(strict_types=1);

namespace Modules\Events\app\Enum;

enum EventCollaboratorRole: string
{
    case CO_HOST = 'co_host';
    case CHECK_IN_AGENT = 'check_in_agent';
    case BOX_OFFICE = 'box_office';

    /**
     * Get default capabilities granted to this collaborator role.
     *
     * @return array<int, string>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::CO_HOST => [
                'event.view',
                'event.update',
                'event.schedule.manage',
                'event.attendees.view',
                'event.tickets.scan',
            ],
            self::CHECK_IN_AGENT => [
                'event.view',
                'event.attendees.view',
                'event.tickets.scan',
            ],
            self::BOX_OFFICE => [
                'event.view',
                'event.tickets.sell',
                'event.attendees.view',
                'event.tickets.scan',
            ],
        };
    }

    public function can(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
