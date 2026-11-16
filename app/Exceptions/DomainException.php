<?php

namespace App\Exceptions;

use RuntimeException;

class DomainException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : __('errors.domain'), $code, $previous);
    }
}
