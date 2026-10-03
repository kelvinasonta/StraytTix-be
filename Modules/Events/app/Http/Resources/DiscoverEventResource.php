<?php

namespace Modules\Events\app\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Events\app\DTOs\RankedEventDTO;

/**
 * @property-read RankedEventDTO $resource
 */
class DiscoverEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dto = $this->resource;
        $event = $dto->event;
        $location = $event->location;
        $schedule = $event->schedule;
        $organizer = $event->organizer;

        // Calculate pricing summary
        $ticketTypes = $event->ticketTypes;
        $minPrice = $ticketTypes->isNotEmpty() ? $ticketTypes->min('price') : 0;
        $maxPrice = $ticketTypes->isNotEmpty() ? $ticketTypes->max('price') : 0;
        $isFree = $minPrice === 0;

        // Days remaining
        $daysRemaining = null;
        if ($schedule && ! empty($schedule->start_date)) {
            $daysRemaining = max(0, Carbon::now()->diffInDays(Carbon::parse($schedule->start_date), false));
        }

        return [
            'uuid' => $event->uuid,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'banner_url' => $event->banner_url,
            'category' => $dto->primaryCategory,
            'categories' => (array) ($event->event_category ?? []),
            'event_type' => $event->event_type?->value ?? 'physical',
            'event_format' => $event->event_format ?? 'in_person',

            'organizer' => $organizer ? [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'is_verified' => (bool) $organizer->is_verified,
            ] : null,

            'venue' => $location ? [
                'name' => $location->venue_name,
                'address' => $location->venue_address,
                'city' => $location->city,
                'state' => $location->state,
                'distance_km' => $dto->distanceKm,
            ] : null,

            'schedule' => $schedule ? [
                'start_date' => $schedule->start_date,
                'end_date' => $schedule->end_date,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'days_remaining' => $daysRemaining,
            ] : null,

            'pricing' => [
                'currency' => 'NGN',
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'is_free' => $isFree,
            ],

            'is_sponsored' => $dto->isSponsored,

            'ranking' => [
                'score' => $dto->finalScore,
                'signals' => $dto->signals,
            ],
        ];
    }
}
