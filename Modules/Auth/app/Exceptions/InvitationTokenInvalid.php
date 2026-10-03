<?php

namespace Modules\Auth\app\Exceptions;

class InvitationTokenInvalid extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Invitation token is invalid.',
            status: 404,
            errorCode: 'INVITATION_TOKEN_INVALID',
        );
    }
}
