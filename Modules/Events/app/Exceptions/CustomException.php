<?php

namespace Modules\Organizer\app\Exceptions;

class CustomException extends OrganizerException
{
    public function __construct($msg, $code, $data)
    {
        parent::__construct(
            message: $msg,
            status: $code,
            errorCode: 'CUSTOM_EXCEPTION',
            meta: $data,
        );
    }
}
