<?php

namespace Modules\Organizer\app\Exceptions;

class OrganizerInvitationNotFoundException extends OrganizerException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Organizer invitation not found.',
            status: 404,
            errorCode: 'INVITATION_NOT_FOUND',
        );
    }
}
