<?php

namespace Modules\Events\app\Actions;

use Illuminate\Support\Facades\Schema;
use Modules\Events\app\DTOs\EventScheduleDTO;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventSchedule;

class EventCreateScheduleAction
{
    public function execute(Event $event, EventScheduleDTO $scheduleDto): EventSchedule
    {
        /** @var EventSchedule $schedule */
        $schedule = $event->schedule()->create($scheduleDto->toArray());

        if (Schema::hasColumn('events', 'event_schedule_id')) {
            $event->event_schedule_id = $schedule->id;
            $event->save();
        }

        return $schedule;
    }
}
