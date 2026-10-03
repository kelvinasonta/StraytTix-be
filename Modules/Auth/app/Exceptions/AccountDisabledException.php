<?php

namespace Modules\Auth\app\Exceptions;

class AccountDisabledException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Your account has been disabled.',
            status: 403,
            errorCode: 'ACCOUNT_DISABLED',
        );
    }
}
