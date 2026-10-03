<?php

namespace Modules\Auth\app\Exceptions;

class ExpiredVerificationTokenException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'The verification link has expired. Please request a new one.',
            status: 400,
            errorCode: 'VERIFICATION_LINK_EXPIRED',
        );
    }
}
