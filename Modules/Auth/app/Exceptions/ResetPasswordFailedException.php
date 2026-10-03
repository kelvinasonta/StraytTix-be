<?php

namespace Modules\Auth\app\Exceptions;

class ResetPasswordFailedException extends AuthException
{
    private const ERROR_CODE = 'RESET_PASSWORD_FAILED';

    public function __construct($message = 'Failed to process reset password request.', $status = 500, $errorCode = self::ERROR_CODE)
    {
        parent::__construct(
            message: $message,
            status: $status,
            errorCode: $errorCode
        );
    }
}
