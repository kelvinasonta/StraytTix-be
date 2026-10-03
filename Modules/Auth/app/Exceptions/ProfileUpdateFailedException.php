<?php

namespace Modules\Auth\app\Exceptions;

class ProfileUpdateFailedException extends AuthException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Failed to update profile. Please try again later.',
            status: 400,
            errorCode: 'PROFILE_UPDATE_FAILED',
        );
    }
}
