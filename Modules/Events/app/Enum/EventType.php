<?php

namespace Modules\Events\app\Enum;

enum EventType: string
{
    case PHYSICAL = 'physical';
    case VIRTUAL = 'virtual';
    case HYBRID = 'hybrid';
}
