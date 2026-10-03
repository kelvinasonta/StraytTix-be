<?php

namespace Modules\Auth\app\Exceptions;

class AccountLockedException extends AuthException
{
    public function __construct(int $retryAfter)
    {
        parent::__construct(
            message: 'Too many login attempts.',
            status: 429,
            errorCode: 'LOGIN_RATE_LIMITED',
            meta: [
                'retry_after' => $retryAfter,
            ]
        );
    }
}
