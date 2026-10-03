<?php

namespace Modules\Auth\app\Exceptions;

class EmailUnverifiedException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Your email address has not been verified.',
            status: 403,
            errorCode: 'EMAIL_NOT_VERIFIED',
        );
    }
}
