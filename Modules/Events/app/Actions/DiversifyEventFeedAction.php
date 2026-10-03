<?php

namespace Modules\Events\app\Actions;

use Illuminate\Support\Collection;
use Modules\Events\app\DTOs\RankedEventDTO;

class DiversifyEventFeedAction
{
    /**
     * Re-rank scored candidates to enforce organizer caps and category variety.
     *
     * @param  Collection<int, RankedEventDTO>  $rankedEvents
     * @return Collection<int, RankedEventDTO>
     */
    public function execute(Collection $rankedEvents, int $limit = 20): Collection
    {
        $feed = collect();
        $remaining = $rankedEvents->values();
        $organizerCounts = [];
        $lastCategory = null;
        $consecutiveCategoryCount = 0;

        while ($feed->count() < $limit && $remaining->isNotEmpty()) {
            $selectedKey = null;

            foreach ($remaining as $key => $candidate) {
                $orgId = $candidate->event->organizer_id;
                $category = $candidate->primaryCategory;

                // Rule 1: Limit organizer domination (max 2 per organizer in top 10)
                if ($feed->count() < 10 && ($organizerCounts[$orgId] ?? 0) >= 2) {
                    continue;
                }

                // Rule 2: Prevent more than 2 consecutive identical categories
                if ($lastCategory !== null && strcasecmp($lastCategory, $category) === 0 && $consecutiveCategoryCount >= 2) {
                    continue;
                }

                $selectedKey = $key;
                break;
            }

            // Fallback: If constraints exhaust remaining items
            if ($selectedKey === null) {
                if ($feed->count() < 10) {
                    // In top 10: we can relax the consecutive category rule, but STRICTLY ENFORCE organizer cap!
                    foreach ($remaining as $key => $candidate) {
                        $orgId = $candidate->event->organizer_id;
                        if (($organizerCounts[$orgId] ?? 0) < 2) {
                            $selectedKey = $key;
                            break;
                        }
                    }

                    // If all remaining candidates belong to already-capped organizers, stop filling top 10
                    if ($selectedKey === null) {
                        break;
                    }
                } else {
                    // Beyond position 10, relax organizer cap
                    $selectedKey = $remaining->keys()->first();
                }
            }

            if ($selectedKey === null) {
                break;
            }

            $winner = $remaining->pull($selectedKey);
            $feed->push($winner);

            // Update state
            $orgId = $winner->event->organizer_id;
            $organizerCounts[$orgId] = ($organizerCounts[$orgId] ?? 0) + 1;

            if ($lastCategory !== null && strcasecmp($lastCategory, $winner->primaryCategory) === 0) {
                $consecutiveCategoryCount++;
            } else {
                $lastCategory = $winner->primaryCategory;
                $consecutiveCategoryCount = 1;
            }
        }

        return $feed;
    }
}
