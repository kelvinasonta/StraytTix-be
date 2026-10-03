<?php

namespace Modules\Organizer\app\Exceptions;

class OrganizerFetchFailed extends OrganizerException
{
    public function __construct()
    {
        parent::__construct(
            message: "Couldn't fetch organizer",
            status: 400,
            errorCode: 'ORGANIZER_FETCH_FAILED',
        );
    }
}
