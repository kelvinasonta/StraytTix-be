<?php

namespace Modules\Events\app\Actions;

use Modules\Events\app\DTOs\EventLocationDTO;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventLocation;

class EventLocationCreateAction
{
    public function execute(Event $event, EventLocationDTO $data): EventLocation
    {
        /** @var EventLocation $location */
        $location = $event->location()->create($data->toArray());

        $event->event_location_id = $location->id ? $location->id : null;

        $event->save();

        return $location;
    }
}
