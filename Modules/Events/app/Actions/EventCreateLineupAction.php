<?php

namespace Modules\Events\app\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Events\app\DTOs\EventLineupDTO;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventLineup;

class EventCreateLineupAction
{
    /**
     * @param  array<int, EventLineupDTO>  $lineupDtos
     * @return Collection<int, EventLineup>
     */
    public function execute(Event $event, array $lineupDtos): Collection
    {
        $lineups = collect();

        foreach ($lineupDtos as $index => $lineupDto) {
            $data = $lineupDto->toArray();
            if (empty($data['sort_order'])) {
                $data['sort_order'] = $index;
            }

            $lineups->push($event->lineup()->create($data));
        }

        if ($lineups->isNotEmpty() && Schema::hasColumn('events', 'event_lineup_id')) {
            $event->event_lineup_id = $lineups->first()->id;
            $event->save();
        }

        return $lineups;
    }
}
