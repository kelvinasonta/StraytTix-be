<?php

namespace Modules\Auth\app\Exceptions;

class RegistrationFailedException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Registration failed. Please try again later or contact support.',
            status: 400,
            errorCode: 'REGISTRATION_FAILED',
        );
    }
}
