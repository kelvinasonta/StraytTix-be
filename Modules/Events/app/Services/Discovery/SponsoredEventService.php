<?php

namespace Modules\Events\app\Services\Discovery;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Events\app\DTOs\RankedEventDTO;
use Modules\Events\app\DTOs\UserContextDTO;
use Modules\Events\app\Models\Event;

class SponsoredEventService
{
    /**
     * Inject relevant sponsored events into reserved slots (Slot 1 and Slot 7).
     *
     * @param  Collection<int, RankedEventDTO>  $organicFeed
     * @return Collection<int, RankedEventDTO>
     */
    public function injectSponsoredEvents(
        Collection $organicFeed,
        UserContextDTO $context,
        int $page = 1
    ): Collection {
        // Only inject sponsored events on page 1 of Discover
        if ($page !== 1) {
            return $organicFeed;
        }

        // 1. Fetch eligible promoted events (featured and published)
        $today = Carbon::today()->toDateString();
        $sponsoredCandidates = Event::query()
            ->published()
            ->public()
            ->where('is_featured', true)
            ->whereHas('schedule', fn ($q) => $q->whereDate('start_date', '>=', $today))
            ->with(['organizer', 'location', 'schedule', 'metrics', 'ticketTypes'])
            ->take(5)
            ->get();

        if ($sponsoredCandidates->isEmpty()) {
            return $organicFeed;
        }

        $feed = $organicFeed->values();
        $injectedCount = 0;
        $targetSlots = [0, 6]; // 1st position and 7th position (0-indexed)

        foreach ($sponsoredCandidates as $sponsorEvent) {
            if ($injectedCount >= count($targetSlots)) {
                break;
            }

            // Quality and relevance check: exclude irrelevant distant physical events
            if ($context->city !== null && $sponsorEvent->location && $sponsorEvent->event_type?->value === 'physical') {
                if ($sponsorEvent->location->city !== null &&
                    strcasecmp($sponsorEvent->location->city, $context->city) !== 0 &&
                    $sponsorEvent->location->state !== null &&
                    strcasecmp($sponsorEvent->location->state, (string) $context->state) !== 0) {
                    continue; // Skip geographically irrelevant sponsor
                }
            }

            // Remove any organic duplicate of this event
            $feed = $feed->reject(fn (RankedEventDTO $item) => $item->event->id === $sponsorEvent->id)->values();

            $primaryCategory = 'general';
            if (is_array($sponsorEvent->event_category) && ! empty($sponsorEvent->event_category)) {
                $primaryCategory = (string) $sponsorEvent->event_category[0];
            }

            $sponsoredDto = new RankedEventDTO(
                event: $sponsorEvent,
                finalScore: 1.0, // Guaranteed slot
                primaryCategory: $primaryCategory,
                signals: ['sponsored_slot' => true],
                isSponsored: true
            );

            $targetIndex = min($targetSlots[$injectedCount], $feed->count());
            $feed->splice($targetIndex, 0, [$sponsoredDto]);
            $injectedCount++;
        }

        return $feed;
    }
}
