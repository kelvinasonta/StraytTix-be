<?php

namespace Modules\Auth\app\Exceptions;

class InvalidResetTokenException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Invalid password reset token.',
            status: 400,
            errorCode: 'INVALID_RESET_TOKEN',
        );
    }
}
