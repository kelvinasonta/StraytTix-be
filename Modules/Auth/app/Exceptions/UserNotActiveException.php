<?php

namespace Modules\Auth\app\Exceptions;

use Modules\Auth\app\Enum\UserStatus;

class UserNotActiveException extends AuthException
{
    public function __construct(UserStatus $status)
    {
        parent::__construct(
            message: "Your account is currently {$status->name}.",
            status: 401,
            errorCode: 'USER_NOT_ACTIVE',
        );
    }
}
