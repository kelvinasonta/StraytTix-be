<?php

namespace Modules\Events\app\Services\Discovery;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Events\app\DTOs\DiscoverFilterDTO;
use Modules\Events\app\DTOs\UserContextDTO;
use Modules\Events\app\Models\Event;

class CandidateRetrievalService
{
    /**
     * Retrieve candidate events matching query constraints and location bounds.
     *
     * @return Collection<int, Event>
     */
    public function retrieveCandidates(DiscoverFilterDTO $filters, UserContextDTO $context, int $candidateLimit = 200): Collection
    {
        $query = Event::query()
            ->published()
            ->public()
            ->with(['organizer', 'location', 'schedule', 'metrics', 'ticketTypes']);

        // 1. Exclude past events: event must be starting in the future or ongoing
        $today = Carbon::today()->toDateString();
        $query->whereHas('schedule', function (Builder $q) use ($today) {
            $q->where(function (Builder $sub) use ($today) {
                $sub->whereDate('start_date', '>=', $today)
                    ->orWhere(function (Builder $ongoing) use ($today) {
                        $ongoing->whereNotNull('end_date')
                            ->whereDate('end_date', '>=', $today);
                    });
            });
        });

        // 2. Explicit User Search Filter
        if (! empty($filters->search)) {
            $term = '%'.strtolower($filters->search).'%';
            $query->where(function (Builder $q) use ($term) {
                $q->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
            });
        }

        // 3. Explicit Category Filter
        if (! empty($filters->category)) {
            $category = $filters->category;
            $query->where(function (Builder $q) use ($category) {
                $q->whereJsonContains('event_category', $category)
                    ->orWhere('event_category', 'like', '%'.$category.'%');
            });
        }

        // 4. Explicit Event Type Filter (physical, online, hybrid)
        if (! empty($filters->eventType)) {
            $query->where('event_type', $filters->eventType);
        }

        // 5. Geographic Constraint
        // Priority 1: User explicitly filtered by City
        if (! empty($filters->city)) {
            $city = $filters->city;
            $query->where(function (Builder $q) use ($city) {
                $q->whereHas('location', fn (Builder $loc) => $loc->whereRaw('LOWER(city) = ?', [strtolower($city)]))
                    ->orWhere('event_type', 'online');
            });
        } elseif (! empty($filters->state)) {
            $state = $filters->state;
            $query->where(function (Builder $q) use ($state) {
                $q->whereHas('location', fn (Builder $loc) => $loc->whereRaw('LOWER(state) = ?', [strtolower($state)]))
                    ->orWhere('event_type', 'online');
            });
        }

        // 6. Date Filter
        if (! empty($filters->date)) {
            $this->applyDateFilter($query, $filters->date);
        }

        // 7. Price Filter
        if (! empty($filters->priceRange)) {
            $this->applyPriceFilter($query, $filters->priceRange);
        }

        return $query->take($candidateLimit)->get();
    }

    protected function applyDateFilter(Builder $query, string $dateFilter): void
    {
        $now = Carbon::now();

        match (strtolower($dateFilter)) {
            'today' => $query->whereHas('schedule', fn (Builder $q) => $q->whereDate('start_date', $now->toDateString())),
            'tomorrow' => $query->whereHas('schedule', fn (Builder $q) => $q->whereDate('start_date', $now->copy()->addDay()->toDateString())),
            'this_weekend' => $query->whereHas('schedule', function (Builder $q) use ($now) {
                $friday = $now->copy()->next(Carbon::FRIDAY)->toDateString();
                $sunday = $now->copy()->next(Carbon::SUNDAY)->toDateString();
                $q->whereBetween('start_date', [$friday, $sunday]);
            }),
            'this_month' => $query->whereHas('schedule', function (Builder $q) use ($now) {
                $start = $now->copy()->startOfMonth()->toDateString();
                $end = $now->copy()->endOfMonth()->toDateString();
                $q->whereBetween('start_date', [$start, $end]);
            }),
            default => null,
        };
    }

    protected function applyPriceFilter(Builder $query, string $priceFilter): void
    {
        match (strtolower($priceFilter)) {
            'free' => $query->whereHas('ticketTypes', fn (Builder $q) => $q->where('price', 0)),
            'paid' => $query->whereHas('ticketTypes', fn (Builder $q) => $q->where('price', '>', 0)),
            'under_5k' => $query->whereHas('ticketTypes', fn (Builder $q) => $q->where('price', '<=', 5000)),
            'under_20k' => $query->whereHas('ticketTypes', fn (Builder $q) => $q->where('price', '<=', 20000)),
            default => null,
        };
    }
}
