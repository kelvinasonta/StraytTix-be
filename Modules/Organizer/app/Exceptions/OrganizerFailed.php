<?php

namespace Modules\Organizer\app\Exceptions;

class OrganizerFailed extends OrganizerException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Failed to Organize.',
            status: 404,
            errorCode: 'VERIFY_EMAIL_FAILED',
        );
    }
}
