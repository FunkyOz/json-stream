<?php

declare(strict_types=1);

namespace JsonStream\Exception;

/**
 * Exception thrown when file or stream I/O operations fail
 *
 * Indicates problems with reading from or writing to files
 * and streams, such as permission denied, file not found,
 * or write failures.
 */
class IOException extends JsonStreamException
{
    protected ?string $filePath = null;

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function __toString(): string
    {
        $message = parent::__toString();

        if ($this->filePath !== null) {
            $message .= sprintf(' (file: %s)', $this->filePath);
        }

        return $message;
    }
}
