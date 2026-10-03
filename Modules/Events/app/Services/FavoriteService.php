<?php

namespace Modules\Events\app\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Actions\AddFavoriteAction;
use Modules\Events\app\Actions\DeleteFavoriteAction;
use Modules\Events\app\Actions\GetFavoriteAction;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\Favorite;

class FavoriteService
{
    public function __construct(
        private AddFavoriteAction $addFavoriteAction,
        private DeleteFavoriteAction $deleteFavoriteAction,
        private GetFavoriteAction $getFavoriteAction,
    ) {}

    /**
     * Retrieve the user's favorited events with optional filters and pagination.
     */
    public function getFavorites(User $user, array $filters = []): LengthAwarePaginator|Collection
    {
        return $this->getFavoriteAction->execute($user, $filters);
    }

    /**
     * Backward-compatibility helper for getFavorites.
     */
    public function getFavoriteAction(User $user): LengthAwarePaginator|Collection
    {
        return $this->getFavorites($user);
    }

    /**
     * Add an event to the user's favorites.
     */
    public function addFavorite(User $user, string $eventIdentifier): Favorite
    {
        return $this->addFavoriteAction->execute($user, $eventIdentifier);
    }

    /**
     * Delete an event from the user's favorites.
     */
    public function deleteFavorite(User $user, string $eventIdentifier): bool
    {
        return $this->deleteFavoriteAction->execute($user, $eventIdentifier);
    }

    /**
     * Toggle an event's favorite status.
     *
     * @return array{favorited: bool, message: string, favorite?: Favorite}
     */
    public function toggleFavorite(User $user, string $eventIdentifier): array
    {
        $isFavorited = $this->isFavorited($user, $eventIdentifier);

        if ($isFavorited) {
            $this->deleteFavorite($user, $eventIdentifier);

            return [
                'favorited' => false,
                'message' => 'Event removed from favorites.',
            ];
        }

        $favorite = $this->addFavorite($user, $eventIdentifier);

        return [
            'favorited' => true,
            'message' => 'Event added to favorites.',
            'favorite' => $favorite,
        ];
    }

    /**
     * Check if an event is favorited by the user.
     */
    public function isFavorited(User $user, string $eventIdentifier): bool
    {
        $event = is_numeric($eventIdentifier)
            ? Event::find($eventIdentifier)
            : Event::where('uuid', $eventIdentifier)->first();

        if (! $event) {
            return false;
        }

        return Favorite::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->exists();
    }

    /**
     * Retrieve a list of event UUIDs favorited by the user.
     *
     * @return string[]
     */
    public function getFavoriteIds(User $user): array
    {
        return Favorite::where('favorites.user_id', $user->id)
            ->join('events', 'events.id', '=', 'favorites.event_id')
            ->pluck('events.uuid')
            ->toArray();
    }

    /**
     * Synchronize a list of event identifiers into the user's favorites.
     *
     * @param  string[]  $eventIdentifiers
     * @return string[] Synced event UUIDs
     */
    public function syncFavorites(User $user, array $eventIdentifiers): array
    {
        $synced = [];

        foreach ($eventIdentifiers as $identifier) {
            try {
                $favorite = $this->addFavorite($user, (string) $identifier);
                $synced[] = $favorite->event?->uuid ?? (string) $identifier;
            } catch (\Throwable $e) {
                // Ignore missing/invalid events during bulk sync
            }
        }

        return array_values(array_unique($synced));
    }
}
