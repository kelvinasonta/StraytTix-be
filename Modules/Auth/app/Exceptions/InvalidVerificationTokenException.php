<?php

namespace Modules\Auth\app\Exceptions;

class InvalidVerificationTokenException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Invalid verification link.',
            status: 400,
            errorCode: 'INVALID_VERIFICATION_LINK',
        );
    }
}
