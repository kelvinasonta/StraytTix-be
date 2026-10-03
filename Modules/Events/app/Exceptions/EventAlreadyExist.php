<?php

namespace Modules\Events\app\Exceptions;

class EventAlreadyExist extends EventException
{
    public function __construct(string $title)
    {
        parent::__construct(
            message: "Event with title {$title} already exist",
            status: 422,
            errorCode: 'EVENT_ALREADY_EXIST',
        );
    }
}
