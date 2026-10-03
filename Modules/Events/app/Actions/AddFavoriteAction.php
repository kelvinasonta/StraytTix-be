<?php

namespace Modules\Events\app\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\Favorite;

class AddFavoriteAction
{
    /**
     * Add an event to the user's favorites.
     *
     * @param  string  $eventIdentifier  Event UUID or numeric ID
     *
     * @throws ModelNotFoundException
     */
    public function execute(User $user, string $eventIdentifier): Favorite
    {
        $event = is_numeric($eventIdentifier)
            ? Event::find($eventIdentifier)
            : Event::where('uuid', $eventIdentifier)->first();

        if (! $event) {
            throw new ModelNotFoundException('Event not found.');
        }

        $favorite = Favorite::firstOrCreate([
            'user_id' => $user->id,
            'event_id' => $event->id,
        ]);

        return $favorite->load(['event.organizer', 'event.location']);
    }
}
