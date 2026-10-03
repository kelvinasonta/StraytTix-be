<?php

declare(strict_types=1);

namespace Modules\Events\app\Actions;

use Modules\Events\app\DTOs\EventSettingDTO;
use Modules\Events\app\Models\Event;
use Modules\Events\app\Models\EventSetting;

class EventSettingCreateAction
{
    public function execute(Event $event, EventSettingDTO $data): EventSetting
    {
        return $event->settings()->create($data->toArray());
    }
}
