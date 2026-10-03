<?php

namespace Modules\Auth\app\Exceptions;

class EmailAlreadyVerifiedException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Email already verified.',
            status: 400,
            errorCode: 'EMAIL_ALREADY_VERIFIED',
        );
    }
}
