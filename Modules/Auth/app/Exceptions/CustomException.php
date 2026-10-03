<?php

namespace Modules\Auth\app\Exceptions;

class CustomException extends AuthException
{
    public function __construct(private $data)
    {
        parent::__construct(
            message: 'Custom exception',
            status: 401,
            errorCode: 'CUSTOM_EXCEPTION',
            meta: $data,
        );
    }

    public function meta(): array
    {
        return $this->data;
    }
}
