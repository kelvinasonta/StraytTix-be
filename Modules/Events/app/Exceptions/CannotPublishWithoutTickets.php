<?php

namespace Modules\Events\app\Exceptions;

class CannotPublishWithoutTickets extends EventException
{
    public function __construct()
    {
        parent::__construct(
            message: 'You cannot publish an event without tickets. Please create at least one ticket tier first.',
            status: 422,
            errorCode: 'EVENT_NO_TICKETS',
        );
    }
}
