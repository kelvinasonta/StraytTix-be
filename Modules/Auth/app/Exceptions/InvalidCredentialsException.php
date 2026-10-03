<?php

namespace Modules\Auth\app\Exceptions;

class InvalidCredentialsException extends AuthException
{
    private const ERROR_CODE = 'INVALID_CREDENTIALS';

    public function __construct()
    {
        parent::__construct(
            message: 'Invalid credentials.',
            status: 401,
            errorCode: self::ERROR_CODE
        );
    }
}
