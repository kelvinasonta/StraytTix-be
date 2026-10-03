<?php

namespace Modules\Auth\app\Exceptions;

class AccountStatusException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Your account is not active. Please contact support.',
            status: 401,
            errorCode: 'ACCOUNT_NOT_ACTIVE',
        );
    }
}
