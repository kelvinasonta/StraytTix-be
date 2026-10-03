<?php

namespace Modules\Events\app\Enum;

enum EventVisibility: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
    case UNLISTED = 'unlisted';
    case DELETED = 'deleted';
}
