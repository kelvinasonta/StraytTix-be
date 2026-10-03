<?php

namespace Modules\Events\app\Services;

use Illuminate\Support\Collection;
use Modules\Events\app\Actions\DiversifyEventFeedAction;
use Modules\Events\app\DTOs\DiscoverFilterDTO;
use Modules\Events\app\DTOs\RankedEventDTO;
use Modules\Events\app\DTOs\UserContextDTO;
use Modules\Events\app\Models\UserInterest;
use Modules\Events\app\Services\Discovery\CandidateRetrievalService;
use Modules\Events\app\Services\Discovery\EventScoringEngine;
use Modules\Events\app\Services\Discovery\SponsoredEventService;

class EventDiscoveryService
{
    public function __construct(
        protected CandidateRetrievalService $candidateService,
        protected EventScoringEngine $scoringEngine,
        protected DiversifyEventFeedAction $diversifier,
        protected SponsoredEventService $sponsoredService
    ) {}

    /**
     * Coordinate full candidate retrieval, scoring, diversification, and sponsored placement.
     *
     * @return array{data: Collection<int, RankedEventDTO>, meta: array<string, mixed>}
     */
    public function discover(DiscoverFilterDTO $filters, UserContextDTO $context): array
    {
        // 1. Hydrate User Personalization Affinities if authenticated
        $hydratedContext = $this->hydrateUserAffinities($context);

        // 2. Candidate Retrieval via SQL
        $candidates = $this->candidateService->retrieveCandidates($filters, $hydratedContext);

        // 3. Multi-Factor Scoring
        $scored = $candidates->map(function ($event) use ($hydratedContext) {
            return $this->scoringEngine->score($event, $hydratedContext);
        });

        // 4. Sort Candidates based on selected sort criteria
        $sorted = match ($filters->sort) {
            'upcoming' => $scored->sortBy(fn (RankedEventDTO $item) => $item->event->schedule?->start_date ?? '9999-12-31'),
            'popular' => $scored->sortByDesc(fn (RankedEventDTO $item) => $item->signals['popularity'] ?? 0.0),
            default => $scored->sortByDesc('finalScore'),
        };

        // 5. Enforce Feed Diversification
        $diversified = $this->diversifier->execute($sorted, $filters->perPage);

        // 6. Transparent Sponsored Event Insertion (Slots 1 & 7)
        $finalFeed = $this->sponsoredService->injectSponsoredEvents(
            $diversified,
            $hydratedContext,
            $filters->page
        );

        return [
            'data' => $finalFeed,
            'meta' => [
                'page' => $filters->page,
                'per_page' => $filters->perPage,
                'returned' => $finalFeed->count(),
                'location_context' => [
                    'city' => $hydratedContext->city,
                    'state' => $hydratedContext->state,
                    'has_coordinates' => $hydratedContext->latitude !== null && $hydratedContext->longitude !== null,
                ],
                'sort_applied' => $filters->sort,
            ],
        ];
    }

    /**
     * Load user category and topic affinities.
     */
    protected function hydrateUserAffinities(UserContextDTO $context): UserContextDTO
    {
        if ($context->userId === null) {
            return $context;
        }

        $affinities = UserInterest::query()
            ->where('user_id', $context->userId)
            ->where('interest_type', 'category')
            ->pluck('affinity_score', 'interest_value')
            ->toArray();

        return $context->withAffinities($affinities);
    }
}
