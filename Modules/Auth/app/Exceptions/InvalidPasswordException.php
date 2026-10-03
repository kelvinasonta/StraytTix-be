<?php

namespace Modules\Auth\app\Exceptions;

class InvalidPasswordException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Current password does not match.',
            status: 422,
            errorCode: 'INVALID_PASSWORD',
        );
    }
}
