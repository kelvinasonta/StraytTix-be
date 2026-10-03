<?php

namespace Modules\Organizer\app\Exceptions;

class OrganizerInvitationAlreadyAcceptedException extends OrganizerException
{
    public function __construct()
    {
        parent::__construct(
            message: 'This invitation has already been accepted.',
            status: 422,
            errorCode: 'INVITATION_ALREADY_ACCEPTED',
        );
    }
}
