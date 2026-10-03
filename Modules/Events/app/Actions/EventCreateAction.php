<?php

namespace Modules\Events\app\Actions;

use Modules\Auth\app\Models\User;
use Modules\Events\app\DTOs\EventDTO;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;

class EventCreateAction
{
    public function execute(Organizer $organizer, ?User $creator, EventDTO $data): Event
    {
        return Event::create(array_merge($data->toArray(), [
            'organizer_id' => $organizer->id,
            'user_id' => $creator?->id,
        ]));
    }
}
