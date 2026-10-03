<?php

namespace Modules\Organizer\app\Exceptions;

class OrganizerInvitationFailedException extends OrganizerException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Failed to send invitation.',
            status: 500,
            errorCode: 'INVITATION_FAILED',
        );
    }
}
