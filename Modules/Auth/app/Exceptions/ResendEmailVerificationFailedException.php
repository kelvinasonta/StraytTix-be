<?php

namespace Modules\Auth\app\Exceptions;

class ResendEmailVerificationFailedException extends AuthException
{
    private const ERROR_CODE = 'RESEND_EMAIL_VERIFICATION_FAILED';

    public function __construct($message = 'Failed to resend email verification.', $status = 500, $errorCode = self::ERROR_CODE)
    {
        parent::__construct(
            message: $message,
            status: $status,
            errorCode: $errorCode
        );
    }
}
