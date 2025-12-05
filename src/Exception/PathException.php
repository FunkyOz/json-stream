<?php

declare(strict_types=1);

namespace JsonStream\Exception;

/**
 * Exception thrown when JSONPath expression is invalid or evaluation fails
 *
 * Indicates problems with JSONPath syntax or evaluation,
 * such as unsupported operators or malformed expressions.
 */
class PathException extends JsonStreamException
{
    protected string $path = '';

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function __toString(): string
    {
        $message = parent::__toString();

        if ($this->path !== '') {
            $message .= sprintf(' (path: %s)', $this->path);
        }

        return $message;
    }
}
