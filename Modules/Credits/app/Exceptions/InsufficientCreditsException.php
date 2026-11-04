<?php

namespace Modules\Credits\app\Exceptions;

use Exception;

class InsufficientCreditsException extends Exception
{
    public function __construct(
        public readonly int $required,
        public readonly int $available,
        string $message = 'Insufficient credits to perform this transaction.',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRequired(): int
    {
        return $this->required;
    }

    public function getAvailable(): int
    {
        return $this->available;
    }
}
