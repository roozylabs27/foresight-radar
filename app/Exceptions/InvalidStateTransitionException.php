<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class InvalidStateTransitionException extends HttpException
{
    public function __construct(string $message = 'Illegal state transition.', ?\Throwable $previous = null)
    {
        parent::__construct(422, $message, $previous);
    }
}
