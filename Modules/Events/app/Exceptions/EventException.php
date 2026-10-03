<?php

namespace Modules\Events\app\Exceptions;

use Exception;

abstract class EventException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 401,
        protected string $errorCode = 'EVENT_ERROR',
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
