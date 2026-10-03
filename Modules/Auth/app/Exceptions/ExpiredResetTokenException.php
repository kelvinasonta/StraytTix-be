<?php

namespace Modules\Auth\app\Exceptions;

class ExpiredResetTokenException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Password reset token has expired. Please request a new one.',
            status: 400,
            errorCode: 'EXPIRED_RESET_TOKEN',
        );
    }
}
