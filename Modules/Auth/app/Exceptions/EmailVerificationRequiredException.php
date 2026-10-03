<?php

namespace Modules\Auth\app\Exceptions;

class EmailVerificationRequiredException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Email verification required.',
            status: 403,
            errorCode: 'EMAIL_VERIFICATION_REQUIRED',
        );
    }
}
