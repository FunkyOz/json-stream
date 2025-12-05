<?php

declare(strict_types=1);

namespace JsonStream\Internal;

use JsonStream\Config;
use JsonStream\Exception\IOException;

/**
 * Manages buffered I/O operations for efficient stream reading
 *
 * Handles reading from streams in chunks, managing internal buffer state,
 * and tracking position for error reporting. Optimized for minimal memory
 * allocations and efficient refills.
 *
 * @internal
 */
final class BufferManager
{
    private string $buffer = '';

    private int $bufferPosition = 0;

    private int $bufferLength = 0;

    private int $totalBytesRead = 0;

    private bool $eof = false;

    private int $line = 0;

    private int $column = 0;

    /**
     * @param  resource  $stream  Stream resource to read from
     * @param  int  $bufferSize  Buffer size in bytes
     *
     * @throws IOException If stream is invalid or unreadable
     */
    public function __construct(
        private readonly mixed $stream,
        private readonly int $bufferSize = Config::DEFAULT_BUFFER_SIZE
    ) {
        if (! is_resource($this->stream)) {
            throw new IOException('Invalid stream resource');
        }

        $metadata = stream_get_meta_data($this->stream);
        $mode = $metadata['mode'];

        // Check if stream is readable
        // Readable modes start with 'r' or contain '+'
        $isReadable = str_starts_with($mode, 'r') || str_contains($mode, '+');

        if (! $isReadable) {
            throw new IOException('Stream is not readable');
        }

        // Validate buffer size
        if ($this->bufferSize < Config::MIN_BUFFER_SIZE || $this->bufferSize > Config::MAX_BUFFER_SIZE) {
            throw new IOException(sprintf(
                'Buffer size must be between %d and %d bytes',
                Config::MIN_BUFFER_SIZE,
                Config::MAX_BUFFER_SIZE
            ));
        }
    }

    /**
     * Read single byte from stream
     *
     * @return string|null Next byte or null if at EOF
     *
     * @throws IOException If read operation fails
     */
    public function readByte(): ?string
    {
        // Check if we need to refill
        if ($this->bufferPosition >= $this->bufferLength) {
            if (! $this->refillBuffer()) {
                return null; // EOF
            }
        }

        $byte = $this->buffer[$this->bufferPosition++];
        $this->totalBytesRead++;
        $this->trackPosition($byte);

        return $byte;
    }

    /**
     * Peek at byte without consuming it
     *
     * CRITICAL BUG FIX: This method had a bug where peeking beyond the current buffer
     * would return null even when data was available. The issue occurred when:
     * 1. Attempting to peek beyond current buffer (e.g., at position 8192 in 8KB buffer)
     * 2. refillBuffer() is called, which resets bufferPosition to 0
     * 3. The old position value (8192) was still used to check the NEW buffer
     * 4. Since new buffer length is typically < 8192, the check failed incorrectly
     *
     * This caused "Expected comma or closing brace" errors when parsing large arrays
     * (>100 elements) at buffer boundaries, as the Lexer's peek() calls would return
     * null for valid characters, causing the parser to misinterpret the token stream.
     *
     * FIX: Recalculate position after buffer refill using the new bufferPosition (0).
     *
     * @param  int  $offset  Offset from current position (0-based)
     * @return string|null Byte at position or null if beyond EOF
     *
     * @throws IOException If read operation fails
     */
    public function peek(int $offset = 0): ?string
    {
        $pos = $this->bufferPosition + $offset;

        // Check if requested position is in current buffer
        if ($pos < $this->bufferLength) {
            return $this->buffer[$pos];
        }

        // Need more data - refill buffer
        if (! $this->eof) {
            $this->refillBuffer();

            // CRITICAL FIX: After refill, bufferPosition is reset to 0, so we must
            // recalculate position relative to the NEW buffer state.
            // The offset is relative to the current position (which is now 0 after refill)
            $pos = $this->bufferPosition + $offset;

            if ($pos < $this->bufferLength) {
                return $this->buffer[$pos];
            }
        }

        return null;
    }

    /**
     * Read chunk of bytes efficiently
     *
     * @param  int  $size  Number of bytes to read
     * @return string Read bytes (may be less than requested if EOF)
     *
     * @throws IOException If read operation fails
     */
    public function readChunk(int $size): string
    {
        if ($size <= 0) {
            return '';
        }

        $result = '';
        $remaining = $size;

        while ($remaining > 0) {
            // Refill if needed
            if ($this->bufferPosition >= $this->bufferLength) {
                if (! $this->refillBuffer()) {
                    break; // EOF
                }
            }

            $available = $this->bufferLength - $this->bufferPosition;
            $take = min($remaining, $available);

            $chunk = substr($this->buffer, $this->bufferPosition, $take);
            $result .= $chunk;

            $this->bufferPosition += $take;
            $this->totalBytesRead += $take;
            $remaining -= $take;

            // Update position for chunk
            for ($i = 0; $i < $take; $i++) {
                $this->trackPosition($chunk[$i]);
            }
        }

        return $result;
    }

    /**
     * Check if at end of stream
     *
     * @return bool True if at EOF
     */
    public function isEof(): bool
    {
        return $this->eof && $this->bufferPosition >= $this->bufferLength;
    }

    /**
     * Get current line number (0-based internally, returned as is)
     *
     * @return int Current line (0-based)
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Get current column number (0-based internally, returned as is)
     *
     * @return int Current column (0-based)
     */
    public function getColumn(): int
    {
        return $this->column;
    }

    /**
     * Get total bytes read from stream
     *
     * @return int Total bytes read
     */
    public function getTotalBytesRead(): int
    {
        return $this->totalBytesRead;
    }

    /**
     * Reset buffer for seekable streams (no-op for non-seekable)
     */
    public function reset(): void
    {
        $metadata = stream_get_meta_data($this->stream);

        if (! $metadata['seekable']) {
            // No-op for non-seekable streams
            return;
        }

        if (fseek($this->stream, 0, SEEK_SET) === -1) {
            throw new IOException('Failed to seek stream');
        }

        $this->buffer = '';
        $this->bufferPosition = 0;
        $this->bufferLength = 0;
        $this->totalBytesRead = 0;
        $this->line = 0;
        $this->column = 0;
        $this->eof = false;
    }

    /**
     * Refill internal buffer from stream
     *
     * @return bool True if buffer was refilled, false if EOF
     *
     * @throws IOException If read fails
     */
    private function refillBuffer(): bool
    {
        if ($this->eof) {
            return false;
        }

        assert($this->bufferSize > 0);

        $data = fread($this->stream, $this->bufferSize);

        if ($data === false) {
            throw new IOException('Failed to read from stream');
        }

        if ($data === '') {
            $this->eof = true;

            return false;
        }

        // Replace buffer with new data
        $this->buffer = $data;
        $this->bufferPosition = 0;
        $this->bufferLength = strlen($data);

        // Check if stream has reached EOF
        if (feof($this->stream)) {
            $this->eof = true;
        }

        return true;
    }

    /**
     * Track position (line and column) for error reporting
     * Line and column are 0-based internally, caller adds 1 when displaying
     *
     * @param  string  $byte  Byte that was read
     */
    private function trackPosition(string $byte): void
    {
        if ($byte === "\n") {
            $this->line++;
            $this->column = 0;
        } else {
            $this->column++;
        }
    }

    /**
     * Clean up resources
     */
    public function __destruct()
    {
        // Stream is owned by caller (StreamReader), so we don't close it here
    }
}
