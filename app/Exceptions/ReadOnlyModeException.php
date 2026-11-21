<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when something tries to change data while the license puts the
 * system in read-only or locked mode. Rendered as HTTP 423 (Locked).
 */
class ReadOnlyModeException extends HttpException
{
    public function __construct(?string $reason = null)
    {
        parent::__construct(423, $reason ?: 'The system is in read-only mode. Changes cannot be saved.');
    }
}
