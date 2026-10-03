<?php

namespace Modules\Auth\app\Exceptions;

class UserNotFoundException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'User not found.',
            status: 404,
            errorCode: 'USER_NOT_FOUND',
        );
    }
}
