<?php

declare(strict_types=1);

namespace Modules\Events\app\Actions;

use Illuminate\Support\Str;
use Modules\Auth\app\Models\User;
use Modules\Events\app\Enum\EventStatus;
use Modules\Events\app\Models\Event;
use Modules\Organizer\app\Models\Organizer;

class EventDuplicateAction
{
    public function execute(Event $sourceEvent, Organizer $organizer, ?User $creator): Event
    {
        // 1. Replicate basic event
        $newEvent = $sourceEvent->replicate([
            'uuid',
            'slug',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        $newEvent->uuid = (string) Str::uuid();
        $newEvent->organizer_id = $organizer->id;
        $newEvent->user_id = $creator?->id ?? $sourceEvent->user_id;
        $newEvent->title = $sourceEvent->title.' (Copy)';
        $newEvent->slug = Str::slug($newEvent->title).'-'.Str::lower(Str::random(5));
        $newEvent->status = EventStatus::DRAFT;
        $newEvent->save();

        // 2. Clone Location
        if ($sourceEvent->location) {
            $newLocation = $sourceEvent->location->replicate([
                'uuid',
                'event_id',
                'created_at',
                'updated_at',
            ]);
            $newLocation->uuid = (string) Str::uuid();
            $newLocation->event_id = $newEvent->id;
            $newLocation->save();

            $newEvent->event_location_id = $newLocation->id;
            $newEvent->save();
        }

        // 3. Clone Schedule
        if ($sourceEvent->schedule) {
            $newSchedule = $sourceEvent->schedule->replicate([
                'uuid',
                'event_id',
                'created_at',
                'updated_at',
            ]);
            $newSchedule->event_id = $newEvent->id;
            $newSchedule->save();

            $newEvent->event_schedule_id = $newSchedule->id;
            $newEvent->save();
        }

        // 4. Clone Schedule Items
        foreach ($sourceEvent->scheduleItems as $item) {
            $newItem = $item->replicate([
                'uuid',
                'event_id',
                'created_at',
                'updated_at',
            ]);
            $newItem->uuid = (string) Str::uuid();
            $newItem->event_id = $newEvent->id;
            $newItem->save();
        }

        // 5. Clone Lineup
        foreach ($sourceEvent->lineup as $lineupItem) {
            $newLineup = $lineupItem->replicate([
                'uuid',
                'event_id',
                'created_at',
                'updated_at',
            ]);
            $newLineup->uuid = (string) Str::uuid();
            $newLineup->event_id = $newEvent->id;
            $newLineup->save();
        }

        // 6. Clone Ticket Types (with reset sold/reserved counts)
        foreach ($sourceEvent->ticketTypes as $ticketType) {
            $newTicketType = $ticketType->replicate([
                'uuid',
                'event_id',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);
            $newTicketType->uuid = (string) Str::uuid();
            $newTicketType->event_id = $newEvent->id;
            $newTicketType->sold_quantity = 0;
            $newTicketType->reserved_quantity = 0;
            $newTicketType->save();
        }

        // 7. Clone Settings
        if ($sourceEvent->settings) {
            $newSettings = $sourceEvent->settings->replicate([
                'uuid',
                'event_id',
                'created_at',
                'updated_at',
            ]);
            $newSettings->uuid = (string) Str::uuid();
            $newSettings->event_id = $newEvent->id;
            $newSettings->save();
        }

        // 8. Clone Custom Questions
        foreach ($sourceEvent->customQuestions as $question) {
            $newQuestion = $question->replicate([
                'uuid',
                'event_id',
                'created_at',
                'updated_at',
            ]);
            $newQuestion->uuid = (string) Str::uuid();
            $newQuestion->event_id = $newEvent->id;
            $newQuestion->save();
        }

        // 9. Clone Promoters (with reset uses and sales)
        foreach ($sourceEvent->promoters as $promoter) {
            $newPromoter = $promoter->replicate([
                'uuid',
                'event_id',
                'created_at',
                'updated_at',
            ]);
            $newPromoter->uuid = (string) Str::uuid();
            $newPromoter->event_id = $newEvent->id;
            $newPromoter->uses = 0;
            $newPromoter->sales = 0.0;
            $newPromoter->save();
        }

        return $newEvent->load(['location', 'schedule', 'scheduleItems', 'lineup', 'ticketTypes', 'settings', 'customQuestions', 'promoters']);
    }
}
