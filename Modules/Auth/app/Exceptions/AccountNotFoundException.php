<?php

namespace Modules\Auth\app\Exceptions;

class AccountNotFoundException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Account not found',
            status: 404,
            errorCode: 'ACCOUNT_NOT_FOUND',
        );
    }
}
