<?php

declare(strict_types=1);

namespace Modules\Events\app\Actions;

use Illuminate\Support\Collection;
use Modules\Events\app\DTOs\EventPromoterDTO;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventPromoter;

class EventPromoterCreateAction
{
    /**
     * @param  array<int, EventPromoterDTO>  $promoterDtos
     * @return Collection<int, EventPromoter>
     */
    public function execute(Event $event, array $promoterDtos): Collection
    {
        $items = collect();

        foreach ($promoterDtos as $index => $dto) {
            $data = $dto->toArray();
            if (empty($data['sort_order'])) {
                $data['sort_order'] = $index;
            }

            $items->push($event->promoters()->create($data));
        }

        return $items;
    }
}
