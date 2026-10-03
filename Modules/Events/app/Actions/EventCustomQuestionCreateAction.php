<?php

declare(strict_types=1);

namespace Modules\Events\app\Actions;

use Illuminate\Support\Collection;
use Modules\Events\app\DTOs\EventCustomQuestionDTO;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventCustomQuestion;

class EventCustomQuestionCreateAction
{
    /**
     * @param  array<int, EventCustomQuestionDTO>  $questionDtos
     * @return Collection<int, EventCustomQuestion>
     */
    public function execute(Event $event, array $questionDtos): Collection
    {
        $items = collect();

        foreach ($questionDtos as $index => $dto) {
            $data = $dto->toArray();
            if (empty($data['sort_order'])) {
                $data['sort_order'] = $index;
            }

            $items->push($event->customQuestions()->create($data));
        }

        return $items;
    }
}
