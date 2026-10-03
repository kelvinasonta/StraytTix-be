<?php

namespace Modules\Auth\app\Exceptions;

class VerifyEmailFailedException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Failed to verify email.',
            status: 500,
            errorCode: 'VERIFY_EMAIL_FAILED',
        );
    }
}
