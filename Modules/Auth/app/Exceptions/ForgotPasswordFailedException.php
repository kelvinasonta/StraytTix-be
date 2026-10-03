<?php

namespace Modules\Auth\app\Exceptions;

class ForgotPasswordFailedException extends AuthException
{
    private const ERROR_CODE = 'FORGOT_PASSWORD_FAILED';

    public function __construct($message = 'Failed to process forgot password request.', $status = 500, $errorCode = self::ERROR_CODE)
    {
        parent::__construct(
            message: $message,
            status: $status,
            errorCode: $errorCode
        );
    }
}
