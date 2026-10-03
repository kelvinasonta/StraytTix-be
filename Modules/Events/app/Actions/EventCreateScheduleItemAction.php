<?php

namespace Modules\Events\app\Actions;

use Illuminate\Support\Collection;
use Modules\Events\app\DTOs\EventScheduleItemDTO;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventScheduleItem;

class EventCreateScheduleItemAction
{
    /**
     * @param  array<int, EventScheduleItemDTO>  $scheduleItemDtos
     * @return Collection<int, EventScheduleItem>
     */
    public function execute(Event $event, array $scheduleItemDtos): Collection
    {
        $items = collect();

        foreach ($scheduleItemDtos as $index => $itemDto) {
            $data = $itemDto->toArray();
            if (empty($data['sort_order'])) {
                $data['sort_order'] = $index;
            }

            $items->push($event->scheduleItems()->create($data));
        }

        return $items;
    }
}
