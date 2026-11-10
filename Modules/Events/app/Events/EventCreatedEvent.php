<?php

declare(strict_types=1);

namespace Modules\Events\app\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;

class EventCreatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Event $event,
        public Organizer $organizer,
        public User $creator
    ) {}
}
