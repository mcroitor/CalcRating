<?php

namespace App\Exceptions;

use RuntimeException;

class ConcurrentModificationException extends RuntimeException
{
    public function __construct(int $code = 409, ?\Throwable $previous = null)
    {
        parent::__construct('', $code, $previous);
    }
}
