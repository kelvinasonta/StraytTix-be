<?php

namespace Modules\Organizer\app\Exceptions;

class UserAlreadyOrganizerMemberException extends OrganizerException
{
    public function __construct()
    {

        parent::__construct(

            message: 'User is already a team member of this organizer.',

            status: 409,

            errorCode: 'USER_ALREADY_ORGANIZER_MEMBER',

        );
    }
}
