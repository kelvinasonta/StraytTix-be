<?php

namespace Modules\Credits\app\Exceptions;

use Exception;

class InvalidReservationException extends Exception
{
    public function __construct(
        string $message = 'Invalid credit reservation state or operation.',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
