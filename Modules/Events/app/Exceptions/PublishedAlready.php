<?php

namespace Modules\Events\app\Exceptions;

class PublishedAlready extends EventException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Event Already Published.',
            status: 409,
            errorCode: 'EVENT_ALREADY_PUBLISHED',
        );
    }
}
