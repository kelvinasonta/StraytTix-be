<?php

namespace Modules\Auth\app\Exceptions;

class RegistrationClosedException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Registration is currently disabled. Please try again later.',
            status: 403,
            errorCode: 'REGISTRATION_CLOSED',
        );
    }
}
