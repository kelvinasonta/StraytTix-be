<?php

namespace Modules\Organizer\app\Exceptions;

use Exception;

abstract class OrganizerException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 401,
        protected string $errorCode = 'ORGANIZER_ERROR',
        protected array $meta = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function meta(): array
    {
        return $this->meta;
    }
}
