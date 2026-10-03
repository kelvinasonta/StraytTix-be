<?php

namespace Modules\Events\app\Services\Discovery;

use Carbon\Carbon;
use Modules\Events\app\DTOs\RankedEventDTO;
use Modules\Events\app\DTOs\UserContextDTO;
use Modules\Events\app\Models\Event;

class EventScoringEngine
{
    /**
     * Score a candidate event across all dimensions.
     */
    public function score(Event $event, UserContextDTO $context): RankedEventDTO
    {
        $distanceKm = null;
        $locScore = $this->calculateLocationScore($event, $context, $distanceKm);
        $urgScore = $this->calculateUrgencyScore($event);
        $popScore = $this->calculatePopularityScore($event);
        $qualScore = $this->calculateQualityScore($event);
        $affScore = $this->calculateAffinityScore($event, $context);
        $boostScore = $this->calculateExplorationBoost($event);

        // Core Weighted Linear Sum (Weights sum to 1.00)
        $baseScore = (0.25 * $locScore)
            + (0.20 * $urgScore)
            + (0.15 * $popScore)
            + (0.15 * $qualScore)
            + (0.15 * $affScore)
            + (0.10 * $boostScore);

        $inventoryMultiplier = $this->getInventoryMultiplier($event);
        $finalScore = round($baseScore * $inventoryMultiplier, 4);

        $primaryCategory = 'general';
        if (is_array($event->event_category) && ! empty($event->event_category)) {
            $primaryCategory = (string) $event->event_category[0];
        } elseif (is_string($event->event_category) && $event->event_category !== '') {
            $primaryCategory = $event->event_category;
        }

        return new RankedEventDTO(
            event: $event,
            finalScore: $finalScore,
            primaryCategory: $primaryCategory,
            signals: [
                'location_fit' => round($locScore, 3),
                'urgency_fit' => round($urgScore, 3),
                'popularity' => round($popScore, 3),
                'quality' => round($qualScore, 3),
                'personal_affinity' => round($affScore, 3),
                'exploration_boost' => round($boostScore, 3),
                'inventory_multiplier' => round($inventoryMultiplier, 2),
            ],
            isSponsored: false,
            distanceKm: $distanceKm !== null ? round($distanceKm, 2) : null
        );
    }

    /**
     * Location score using Gaussian distance decay or city/state matching.
     */
    public function calculateLocationScore(Event $event, UserContextDTO $context, ?float &$distanceKm = null): float
    {
        if ($event->event_type?->value === 'online' || $event->event_format === 'online') {
            return 0.85; // Universally accessible
        }

        $location = $event->location;
        if (! $location) {
            return 0.10;
        }

        // Exact GPS distance available
        if ($context->latitude !== null && $context->longitude !== null &&
            $location->latitude !== null && $location->longitude !== null) {
            $distanceKm = $this->haversineDistance(
                $context->latitude,
                $context->longitude,
                (float) $location->latitude,
                (float) $location->longitude
            );

            // 25km sigma for Nigerian metro transit zones
            $sigma = 25.0;

            return (float) exp(-($distanceKm ** 2) / (2 * ($sigma ** 2)));
        }

        // Fallback to city or state matching
        if ($context->city !== null && $location->city !== null &&
            strcasecmp($context->city, $location->city) === 0) {
            return 1.00;
        }

        if ($context->state !== null && $location->state !== null &&
            strcasecmp($context->state, $location->state) === 0) {
            return 0.60;
        }

        return 0.10;
    }

    /**
     * Time Urgency: log-normal purchase window peaking 3-10 days before event.
     */
    public function calculateUrgencyScore(Event $event): float
    {
        $schedule = $event->schedule;
        if (! $schedule || empty($schedule->start_date)) {
            return 0.20;
        }

        $startDate = Carbon::parse($schedule->start_date);
        $now = Carbon::now();

        if ($startDate->isPast()) {
            return 0.0; // Past event
        }

        $daysRemaining = $now->diffInDays($startDate, false);
        if ($daysRemaining > 60) {
            return 0.10;
        }

        // Lognormal curve centered around 5-7 days before event
        $logDays = log($daysRemaining + 1);
        $mu = 1.6;
        $sigma = 0.9;

        return (float) exp(-(($logDays - $mu) ** 2) / (2 * ($sigma ** 2)));
    }

    /**
     * Popularity score from precomputed 72-hour metrics.
     */
    public function calculatePopularityScore(Event $event): float
    {
        $metrics = $event->metrics;
        if (! $metrics) {
            return 0.0;
        }

        if ($metrics->popularity_score > 0) {
            return (float) $metrics->popularity_score;
        }

        $raw = (1.0 * $metrics->views_72h)
            + (4.0 * $metrics->shares_72h)
            + (8.0 * $metrics->saves_72h)
            + (25.0 * $metrics->tickets_sold_72h);

        // Normalized sigmoid
        $kappa = log(1 + 250); // ~5.52

        return (float) (log(1 + $raw) / (log(1 + $raw) + $kappa));
    }

    /**
     * Quality score checking content depth and organizer pedigree.
     */
    public function calculateQualityScore(Event $event): float
    {
        if ($event->metrics && $event->metrics->quality_score > 0) {
            return (float) $event->metrics->quality_score;
        }

        $score = 0.0;

        // Banner present
        if (! empty($event->banner_url)) {
            $score += 0.25;
        }

        // Meaningful description
        if (! empty($event->description) && str_word_count($event->description) >= 50) {
            $score += 0.20;
        }

        // Detailed location
        if ($event->location && ! empty($event->location->venue_name)) {
            $score += 0.20;
        }

        // Schedule defined
        if ($event->schedule && ! empty($event->schedule->start_date)) {
            $score += 0.15;
        }

        // Organizer verified or active
        if ($event->organizer && ($event->organizer->is_verified ?? $event->organizer->is_active ?? true)) {
            $score += 0.20;
        }

        return min(1.0, max(0.20, $score));
    }

    /**
     * Personal affinity based on user's category preferences.
     */
    public function calculateAffinityScore(Event $event, UserContextDTO $context): float
    {
        if (empty($context->categoryAffinities)) {
            return 0.50; // Neutral baseline for anonymous or new visitors
        }

        $categories = (array) $event->event_category;
        $highest = 0.10;

        foreach ($categories as $cat) {
            if (isset($context->categoryAffinities[$cat])) {
                $highest = max($highest, (float) $context->categoryAffinities[$cat]);
            }
        }

        return min(1.0, $highest);
    }

    /**
     * Cold-start exploration boost for fresh events (< 500 impressions).
     */
    public function calculateExplorationBoost(Event $event): float
    {
        $impressions = (int) ($event->metrics?->total_impressions ?? 0);
        $budget = 500;

        if ($impressions >= $budget) {
            return 0.0;
        }

        $remainingRatio = (1.0 - ($impressions / $budget));
        $isTrusted = $event->organizer && ($event->organizer->is_verified ?? $event->organizer->is_active ?? true);
        $organizerTrust = $isTrusted ? 1.0 : 0.65;

        return (float) ($remainingRatio * $organizerTrust);
    }

    /**
     * Inventory multiplier: depresses sold out events, boosts scarcity.
     */
    public function getInventoryMultiplier(Event $event): float
    {
        if ($event->status?->value === 'sold_out') {
            return 0.05;
        }

        return 1.00;
    }

    /**
     * Compute Haversine distance in Kilometers.
     */
    protected function haversineDistance(float $latFrom, float $lonFrom, float $latTo, float $lonTo): float
    {
        $earthRadius = 6371.0;

        $latDelta = deg2rad($latTo - $latFrom);
        $lonDelta = deg2rad($lonTo - $lonFrom);

        $a = (sin($latDelta / 2) ** 2) +
            cos(deg2rad($latFrom)) * cos(deg2rad($latTo)) * (sin($lonDelta / 2) ** 2);

        $c = 2 * asin(sqrt($a));

        return $c * $earthRadius;
    }
}
