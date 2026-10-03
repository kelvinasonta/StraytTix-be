<?php

namespace Modules\Events\app\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Models\Event;

class GetFavoriteAction
{
    /**
     * Retrieve published events favorited by the user with filters and optional pagination.
     */
    public function execute(User $user, array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Event::with(['organizer', 'location'])
            ->published()
            ->join('favorites', 'favorites.event_id', '=', 'events.id')
            ->where('favorites.user_id', $user->id)
            ->orderBy('favorites.created_at', 'desc')
            ->select('events.*');

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhereHas('location', function ($lq) use ($search) {
                        $lq->where('venue_name', 'like', $search);
                    });
            });
        }

        if (! empty($filters['eventType'])) {
            $query->where('event_type', $filters['eventType']);
        }

        if (! empty($filters['category']) && strtolower($filters['category']) !== 'all') {
            $cat = '%'.$filters['category'].'%';
            $query->where(function ($q) use ($cat) {
                $q->where('event_format', 'like', $cat)
                    ->orWhere('event_category', 'like', $cat);
            });
        }

        $perPage = $filters['perPage'] ?? $filters['per_page'] ?? null;

        if ($perPage) {
            return $query->paginate((int) $perPage);
        }

        return $query->get();
    }
}
