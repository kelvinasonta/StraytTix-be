<?php

namespace Modules\Organizer\app\Exceptions;

class OrganizerInvitationUnauthorizedException extends OrganizerException
{
    public function __construct()
    {
        parent::__construct(
            message: 'You do not have permission to perform this action.',
            status: 403,
            errorCode: 'INVITATION_UNAUTHORIZED',
        );
    }
}
