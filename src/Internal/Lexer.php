<?php

declare(strict_types=1);

namespace JsonStream\Internal;

use JsonStream\Config;
use JsonStream\Exception\IOException;
use JsonStream\Exception\ParseException;

/**
 * JSON Lexer - converts byte stream to tokens
 *
 * Implements RFC 8259 compliant tokenization with proper Unicode handling,
 * escape sequences, and comprehensive error messages. Manages buffered I/O
 * operations for efficient stream reading internally.
 *
 * @internal
 */
final class Lexer
{
    // Buffer I/O state
    private string $buffer = '';

    private int $bufferPosition = 0;

    private int $bufferLength = 0;

    private int $totalBytesRead = 0;

    private bool $eof = false;

    private int $line = 0;

    private int $column = 0;

    // Lexer state
    private ?Token $peekedToken = null;

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
     * Get next token from stream
     *
     * @return Token Next token
     *
     * @throws ParseException On invalid JSON syntax
     */
    public function nextToken(): Token
    {
        if ($this->peekedToken !== null) {
            $token = $this->peekedToken;
            $this->peekedToken = null;

            return $token;
        }

        return $this->scanToken();
    }

    /**
     * Peek at next token without consuming
     *
     * @return Token Next token
     *
     * @throws ParseException On invalid JSON syntax
     */
    public function peekToken(): Token
    {
        if ($this->peekedToken === null) {
            $this->peekedToken = $this->scanToken();
        }

        return $this->peekedToken;
    }

    // ── Buffer I/O Methods ──────────────────────────────────────────────

    /**
     * Read single byte from stream
     *
     * @return string|null Next byte or null if at EOF
     *
     * @throws IOException If read operation fails
     */
    private function readByte(): ?string
    {
        if ($this->bufferPosition >= $this->bufferLength) {
            if (! $this->refillBuffer()) {
                return null;
            }
        }

        $byte = $this->buffer[$this->bufferPosition++];
        $this->totalBytesRead++;

        if ($byte === "\n") {
            $this->line++;
            $this->column = 0;
        } else {
            $this->column++;
        }

        return $byte;
    }

    /**
     * Peek at byte without consuming it
     *
     * @param  int  $offset  Offset from current position (0-based)
     * @return string|null Byte at position or null if beyond EOF
     *
     * @throws IOException If read operation fails
     */
    private function peek(int $offset = 0): ?string
    {
        $pos = $this->bufferPosition + $offset;

        if ($pos < $this->bufferLength) {
            return $this->buffer[$pos];
        }

        if (! $this->eof) {
            $this->refillBuffer();

            // After refill, bufferPosition is reset to 0, so recalculate
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
    private function readChunk(int $size): string
    {
        if ($size <= 0) {
            return '';
        }

        $chunks = [];
        $remaining = $size;

        while ($remaining > 0) {
            if ($this->bufferPosition >= $this->bufferLength) {
                if (! $this->refillBuffer()) {
                    break;
                }
            }

            $available = $this->bufferLength - $this->bufferPosition;
            $take = min($remaining, $available);

            $chunk = substr($this->buffer, $this->bufferPosition, $take);
            $chunks[] = $chunk;

            $this->bufferPosition += $take;
            $this->totalBytesRead += $take;
            $remaining -= $take;

            // Update position for chunk
            for ($i = 0; $i < $take; $i++) {
                if ($chunk[$i] === "\n") {
                    $this->line++;
                    $this->column = 0;
                } else {
                    $this->column++;
                }
            }
        }

        return implode('', $chunks);
    }

    /**
     * Check if at end of stream
     */
    public function isEof(): bool
    {
        return $this->eof && $this->bufferPosition >= $this->bufferLength;
    }

    /**
     * Get current line number (0-based)
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Get current column number (0-based)
     */
    public function getColumn(): int
    {
        return $this->column;
    }

    /**
     * Get total bytes read from stream
     */
    public function getTotalBytesRead(): int
    {
        return $this->totalBytesRead;
    }

    /**
     * Reset buffer for seekable streams (no-op for non-seekable)
     *
     * @throws IOException If seek fails
     */
    public function reset(): void
    {
        $metadata = stream_get_meta_data($this->stream);

        if (! $metadata['seekable']) {
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
        $this->peekedToken = null;
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

        // Ensure buffer size is within valid range for fread (required for PHPStan compatibility across versions)
        $bufferSize = max(1, $this->bufferSize);

        $data = fread($this->stream, $bufferSize);

        if ($data === false) {
            throw new IOException('Failed to read from stream');
        }

        if ($data === '') {
            $this->eof = true;

            return false;
        }

        $this->buffer = $data;
        $this->bufferPosition = 0;
        $this->bufferLength = strlen($data);

        if (feof($this->stream)) {
            $this->eof = true;
        }

        return true;
    }

    // ── Tokenization Methods ────────────────────────────────────────────

    /**
     * Scan next token from buffer
     *
     * @return Token Scanned token
     *
     * @throws ParseException On invalid syntax
     */
    private function scanToken(): Token
    {
        $this->skipWhitespace();

        // Get 0-based position, convert to 1-based for token
        $line = $this->line + 1;
        $column = $this->column + 1;
        $char = $this->readByte();

        if ($char === null) {
            return new Token(TokenType::EOF, null, $line, $column);
        }

        return match ($char) {
            '{' => new Token(TokenType::LEFT_BRACE, null, $line, $column),
            '}' => new Token(TokenType::RIGHT_BRACE, null, $line, $column),
            '[' => new Token(TokenType::LEFT_BRACKET, null, $line, $column),
            ']' => new Token(TokenType::RIGHT_BRACKET, null, $line, $column),
            ':' => new Token(TokenType::COLON, null, $line, $column),
            ',' => new Token(TokenType::COMMA, null, $line, $column),
            '"' => $this->scanString($line, $column),
            't' => $this->scanKeyword('true', true, TokenType::TRUE, $line, $column),
            'f' => $this->scanKeyword('false', false, TokenType::FALSE, $line, $column),
            'n' => $this->scanKeyword('null', null, TokenType::NULL, $line, $column),
            '-', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' => $this->scanNumber($char, $line, $column),
            default => throw $this->error("Unexpected character: '$char'", $line - 1, $column - 1),
        };
    }

    /**
     * Skip whitespace characters with inline buffer access
     */
    private function skipWhitespace(): void
    {
        while (true) {
            // Inline buffer bounds check
            if ($this->bufferPosition >= $this->bufferLength) {
                if (! $this->refillBuffer()) {
                    return;
                }
            }

            $ch = $this->buffer[$this->bufferPosition];

            if ($ch !== ' ' && $ch !== "\n" && $ch !== "\r" && $ch !== "\t") {
                return;
            }

            // Inline position tracking
            if ($ch === "\n") {
                $this->line++;
                $this->column = 0;
            } else {
                $this->column++;
            }

            $this->bufferPosition++;
            $this->totalBytesRead++;
        }
    }

    /**
     * Scan string token with escape handling
     *
     * @param  int  $line  Starting line
     * @param  int  $column  Starting column
     * @return Token String token
     *
     * @throws ParseException On invalid string syntax
     */
    private function scanString(int $line, int $column): Token
    {
        $result = '';

        while (true) {
            $firstByte = $this->readByte();

            if ($firstByte === null) {
                throw $this->error('Unterminated string', $line, $column);
            }

            if ($firstByte === '"') {
                return new Token(TokenType::STRING, $result, $line, $column);
            }

            if ($firstByte === '\\') {
                $result .= $this->parseEscapeSequence();

                continue;
            }

            // Validate control characters (0x00-0x1F are invalid)
            $ord = ord($firstByte);
            if ($ord < 0x20) {
                throw $this->error(
                    sprintf('Invalid control character in string (0x%02x)', $ord),
                    $this->line,
                    $this->column
                );
            }

            // Read complete UTF-8 character
            $char = $this->readUtf8Character($firstByte);

            // Validate UTF-8
            if (! mb_check_encoding($char, 'UTF-8')) {
                throw $this->error(
                    'Invalid UTF-8 sequence in string',
                    $this->line,
                    $this->column
                );
            }

            $result .= $char;
        }
    }

    /**
     * Read a complete UTF-8 character starting with the given first byte
     *
     * @param  string  $firstByte  First byte of UTF-8 sequence
     * @return string Complete UTF-8 character
     */
    private function readUtf8Character(string $firstByte): string
    {
        $ord = ord($firstByte);

        // Single-byte character (ASCII): 0xxxxxxx
        if ($ord < 0x80) {
            return $firstByte;
        }

        // Determine number of bytes in this UTF-8 sequence
        if (($ord & 0xE0) === 0xC0) {
            $additionalBytes = 1;
        } elseif (($ord & 0xF0) === 0xE0) {
            $additionalBytes = 2;
        } elseif (($ord & 0xF8) === 0xF0) {
            $additionalBytes = 3;
        } else {
            return $firstByte;
        }

        $char = $firstByte;
        for ($i = 0; $i < $additionalBytes; $i++) {
            $byte = $this->readByte();
            if ($byte === null) {
                break;
            }
            $char .= $byte;
        }

        return $char;
    }

    /**
     * Parse escape sequence after backslash
     *
     * @return string Decoded character
     *
     * @throws ParseException On invalid escape sequence
     */
    private function parseEscapeSequence(): string
    {
        $char = $this->readByte();

        if ($char === null) {
            throw $this->error(
                'Unterminated escape sequence',
                $this->line,
                $this->column
            );
        }

        return match ($char) {
            '"' => '"',
            '\\' => '\\',
            '/' => '/',
            'b' => "\x08",
            'f' => "\f",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'u' => $this->parseUnicodeEscape(),
            default => throw $this->error(
                "Invalid escape sequence: \\$char",
                $this->line,
                $this->column
            ),
        };
    }

    /**
     * Parse Unicode escape sequence (\uXXXX)
     *
     * Handles UTF-16 surrogate pairs for characters outside the Basic Multilingual Plane.
     *
     * @return string UTF-8 encoded character
     *
     * @throws ParseException On invalid Unicode escape
     */
    private function parseUnicodeEscape(): string
    {
        $hex = $this->readChunk(4);

        if (strlen($hex) !== 4 || ! ctype_xdigit($hex)) {
            throw $this->error(
                "Invalid Unicode escape sequence: \\u$hex",
                $this->line,
                $this->column
            );
        }

        $codepoint = hexdec($hex);

        // Handle UTF-16 surrogate pairs (high surrogate: 0xD800-0xDBFF)
        if ($codepoint >= 0xD800 && $codepoint <= 0xDBFF) {
            if ($this->peek() !== '\\' || $this->peek(1) !== 'u') {
                throw $this->error(
                    'Invalid lone high UTF-16 surrogate: \\u' . $hex,
                    $this->line,
                    $this->column
                );
            }

            $this->readByte(); // consume \
            $this->readByte(); // consume u

            $lowHex = $this->readChunk(4);

            if (strlen($lowHex) !== 4 || ! ctype_xdigit($lowHex)) {
                throw $this->error(
                    "Invalid Unicode escape sequence: \\u$lowHex",
                    $this->line,
                    $this->column
                );
            }

            $lowCodepoint = hexdec($lowHex);

            if ($lowCodepoint < 0xDC00 || $lowCodepoint > 0xDFFF) {
                throw $this->error(
                    'Invalid UTF-16 surrogate pair: expected low surrogate after \\u' . $hex,
                    $this->line,
                    $this->column
                );
            }

            // Combine surrogates into single codepoint
            $codepoint = 0x10000 + (($codepoint & 0x3FF) << 10) + ($lowCodepoint & 0x3FF);
        } elseif ($codepoint >= 0xDC00 && $codepoint <= 0xDFFF) {
            throw $this->error(
                'Invalid lone low UTF-16 surrogate: \\u' . $hex,
                $this->line,
                $this->column
            );
        }

        // Convert codepoint to UTF-8
        $char = mb_chr((int) $codepoint, 'UTF-8');
        // @phpstan-ignore-next-line — mb_chr() can return false for invalid codepoints
        if ($char === false) {
            throw $this->error(
                'Invalid Unicode codepoint: \\u' . $hex,
                $this->line,
                $this->column
            );
        }

        return $char;
    }

    /**
     * Scan number token (integer, float, or scientific notation)
     *
     * @param  string  $firstChar  First character of number
     * @param  int  $line  Starting line
     * @param  int  $column  Starting column
     * @return Token Number token
     *
     * @throws ParseException On invalid number format
     */
    private function scanNumber(string $firstChar, int $line, int $column): Token
    {
        $isFloat = false;
        $isNegative = ($firstChar === '-');

        $intPart = 0;
        $fracPart = 0;
        $fracDigits = 0;
        $expPart = 0;
        $expNegative = false;

        if ($isNegative) {
            $firstChar = $this->readByte();
            if ($firstChar === null || ! ctype_digit($firstChar)) {
                throw $this->error('Expected digit after minus sign', $line, $column);
            }
        }

        // Overflow detection thresholds
        $maxBeforeOverflow = (int) (PHP_INT_MAX / 10);
        $maxLastDigit = PHP_INT_MAX % 10;
        $overflowed = false;

        if ($firstChar === '0') {
            $next = $this->peek();
            if ($next !== null && ctype_digit($next)) {
                throw $this->error('Leading zeros not allowed', $line, $column);
            }
            $intPart = 0;
        } else {
            $intPart = ord($firstChar) - ord('0');

            while (true) {
                $char = $this->peek();
                if ($char === null || ! ctype_digit($char)) {
                    break;
                }
                $this->readByte();
                $digit = ord($char) - ord('0');

                if (! $overflowed && ($intPart > $maxBeforeOverflow
                    || ($intPart === $maxBeforeOverflow && $digit > $maxLastDigit))) {
                    $overflowed = true;
                    $isFloat = true;
                }

                $intPart = $intPart * 10 + $digit;
            }
        }

        // Decimal part
        if ($this->peek() === '.') {
            $isFloat = true;
            $this->readByte();

            $char = $this->peek();
            // @phpstan-ignore-next-line — peek() can return null at EOF/buffer boundary
            if ($char === null) {
                throw $this->error('Expected digit after decimal point', $line, $column);
            }
            // @phpstan-ignore-next-line — defensive: char may be non-digit after buffer refill
            if (! ctype_digit($char)) {
                throw $this->error('Expected digit after decimal point', $line, $column);
            }

            while (true) {
                $char = $this->peek();
                // @phpstan-ignore-next-line — peek() can return null at EOF/buffer boundary
                if ($char === null) {
                    break;
                }
                // @phpstan-ignore-next-line — defensive: char may be non-digit
                if (! ctype_digit($char)) {
                    break;
                }
                $this->readByte();
                $fracPart = $fracPart * 10 + (ord($char) - ord('0'));
                $fracDigits++;
            }
        }

        // Exponent part
        $next = $this->peek();
        if ($next === 'e' || $next === 'E') {
            $isFloat = true;
            $this->readByte();

            $char = $this->peek();
            if ($char === '+' || $char === '-') {
                $expNegative = ($char === '-');
                $this->readByte();
                $char = $this->peek();
            }

            if ($char === null || ! ctype_digit($char)) {
                throw $this->error('Expected digit in exponent', $line, $column);
            }

            while (true) {
                $char = $this->peek();
                if ($char === null || ! ctype_digit($char)) {
                    break;
                }
                $this->readByte();
                $expPart = $expPart * 10 + (ord($char) - ord('0'));
            }

            if ($expNegative) {
                $expPart = -$expPart;
            }
        }

        // Calculate final value
        if ($isFloat) {
            $value = (float) $intPart;

            if ($fracDigits > 0) {
                $value += $fracPart / (10 ** $fracDigits);
            }

            if ($expPart !== 0) {
                $value *= 10 ** $expPart;
            }

            if ($isNegative) {
                $value = -$value;
            }
        } else {
            $value = $isNegative ? -$intPart : $intPart;
        }

        return new Token(TokenType::NUMBER, $value, $line, $column);
    }

    /**
     * Scan keyword token (true, false, null)
     *
     * @param  string  $expected  Expected keyword string
     * @param  mixed  $value  Value to return
     * @param  TokenType  $type  Token type
     * @param  int  $line  Starting line
     * @param  int  $column  Starting column
     * @return Token Keyword token
     *
     * @throws ParseException On invalid keyword
     */
    private function scanKeyword(string $expected, mixed $value, TokenType $type, int $line, int $column): Token
    {
        $len = strlen($expected);

        $remaining = $this->readChunk($len - 1);

        if ($remaining !== substr($expected, 1)) {
            throw $this->error("Invalid keyword, expected '$expected'", $line, $column);
        }

        return new Token($type, $value, $line, $column);
    }

    /**
     * Create ParseException with position information
     *
     * @param  string  $message  Error message
     * @param  int  $line  Line number (0-based)
     * @param  int  $column  Column number (0-based)
     * @return ParseException Exception with position
     */
    private function error(string $message, int $line, int $column): ParseException
    {
        $exception = new ParseException($message);
        $exception->setPosition($line + 1, $column + 1);

        return $exception;
    }
}
