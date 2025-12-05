<?php

declare(strict_types=1);

namespace JsonStream\Exception;

/**
 * Exception thrown when JSON parsing fails
 *
 * Indicates malformed or invalid JSON structure.
 * Includes line and column information for precise
 * error location reporting.
 */
class ParseException extends JsonStreamException
{
    protected int $jsonLine = 0;

    protected int $jsonColumn = 0;

    public function getJsonLine(): int
    {
        return $this->jsonLine;
    }

    public function getJsonColumn(): int
    {
        return $this->jsonColumn;
    }

    public function setPosition(int $line, int $column): void
    {
        $this->jsonLine = $line;
        $this->jsonColumn = $column;
    }

    public function __toString(): string
    {
        $message = parent::__toString();

        if ($this->jsonLine > 0 || $this->jsonColumn > 0) {
            $message .= sprintf(
                ' at line %d, column %d',
                $this->jsonLine,
                $this->jsonColumn
            );
        }

        return $message;
    }
}
