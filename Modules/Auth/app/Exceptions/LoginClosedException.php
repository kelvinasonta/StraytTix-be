<?php

namespace Modules\Auth\app\Exceptions;

class LoginClosedException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Login is currently disabled. Please try again later.',
            status: 403,
            errorCode: 'LOGIN_CLOSED',
        );
    }
}
