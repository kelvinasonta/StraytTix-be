<?php

namespace Modules\Auth\app\Exceptions;

class PasswordMustBeNewException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'New password cannot be the same as current password.',
            status: 422,
            errorCode: 'PASSWORD_MUST_BE_NEW',
        );
    }
}
