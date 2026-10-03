<?php

namespace Modules\Events\app\Actions;

use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\Favorite;

class DeleteFavoriteAction
{
    /**
     * Remove an event from the user's favorites.
     *
     * @param  string  $eventIdentifier  Event UUID, numeric ID, or favorite UUID
     */
    public function execute(User $user, string $eventIdentifier): bool
    {
        // First try finding an event by uuid or numeric id
        $event = is_numeric($eventIdentifier)
            ? Event::find($eventIdentifier)
            : Event::where('uuid', $eventIdentifier)->first();

        if ($event) {
            $deleted = Favorite::where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->delete();

            return $deleted > 0;
        }

        // Fallback: check if the identifier refers to the favorite's uuid or id
        $deleted = Favorite::where('user_id', $user->id)
            ->where(function ($query) use ($eventIdentifier) {
                $query->where('uuid', $eventIdentifier);
                if (is_numeric($eventIdentifier)) {
                    $query->orWhere('id', $eventIdentifier);
                }
            })
            ->delete();

        return $deleted > 0;
    }
}
