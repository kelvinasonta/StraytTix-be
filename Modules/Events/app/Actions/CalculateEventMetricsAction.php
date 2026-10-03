<?php

namespace Modules\Events\app\Actions;

use Carbon\Carbon;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventMetric;

class CalculateEventMetricsAction
{
    /**
     * Compute and persist rolling quality and popularity metrics for an event.
     */
    public function execute(Event $event): EventMetric
    {
        $metric = $event->metrics ?? new EventMetric(['event_id' => $event->id]);

        // 1. Calculate Content Quality Score
        $qualityScore = 0.0;
        if (! empty($event->banner_url)) {
            $qualityScore += 0.25;
        }
        if (! empty($event->description) && str_word_count($event->description) >= 50) {
            $qualityScore += 0.20;
        }
        if ($event->location && ! empty($event->location->venue_name)) {
            $qualityScore += 0.20;
        }
        if ($event->schedule && ! empty($event->schedule->start_date)) {
            $qualityScore += 0.15;
        }
        if ($event->organizer && $event->organizer->is_verified) {
            $qualityScore += 0.20;
        }

        // 2. Calculate Popularity Velocity Score (72-hour rolling window)
        $rawEngagement = (1.0 * ($metric->views_72h ?? 0))
            + (4.0 * ($metric->shares_72h ?? 0))
            + (8.0 * ($metric->saves_72h ?? 0))
            + (25.0 * ($metric->tickets_sold_72h ?? 0));

        $kappa = log(1 + 250);
        $popularityScore = (float) (log(1 + $rawEngagement) / (log(1 + $rawEngagement) + $kappa));

        $metric->quality_score = min(1.0, max(0.20, $qualityScore));
        $metric->popularity_score = round($popularityScore, 4);
        $metric->last_scored_at = Carbon::now();
        $metric->save();

        return $metric;
    }
}
