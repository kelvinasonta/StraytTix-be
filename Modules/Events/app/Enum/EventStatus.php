<?php

namespace Modules\Events\app\Enum;

enum EventStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
    case ARCHIVED = 'archived';
    case DELETED = 'deleted';
}
