<?php

declare(strict_types=1);

namespace JsonStream\Exception;

use Exception;

/**
 * Base exception class for all JsonStream errors
 *
 * All JsonStream exceptions extend this class, allowing
 * applications to catch all library-specific exceptions
 * with a single catch block.
 */
class JsonStreamException extends Exception
{
    protected string $context = '';

    public function getContext(): string
    {
        return $this->context;
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }
}
