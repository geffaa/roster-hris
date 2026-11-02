<?php

namespace App\Exceptions;

use RuntimeException;

/** A clock-in/out refused for a reason the employee can act on; the message is shown as-is. */
class ClockException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
