<?php

declare(strict_types=1);

namespace JsonStream\Internal;

use JsonStream\Exception\ParseException;

/**
 * JSON Lexer - converts byte stream to tokens
 *
 * Implements RFC 8259 compliant tokenization with proper Unicode handling,
 * escape sequences, and comprehensive error messages.
 *
 * @internal
 */
final class Lexer
{
    private ?Token $peekedToken = null;

    /**
     * @param  BufferManager  $buffer  Input buffer
     */
    public function __construct(
        private readonly BufferManager $buffer
    ) {}

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

        // Get 0-based position from buffer, convert to 1-based for token
        $line = $this->buffer->getLine() + 1;
        $column = $this->buffer->getColumn() + 1;
        $char = $this->buffer->readByte();

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
     * Skip whitespace characters
     */
    private function skipWhitespace(): void
    {
        while (true) {
            $char = $this->buffer->peek();

            if ($char === null) {
                return;
            }

            if ($char === ' ' || $char === "\n" || $char === "\r" || $char === "\t") {
                $this->buffer->readByte();

                continue;
            }

            return;
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
            $firstByte = $this->buffer->readByte();

            if ($firstByte === null) {
                throw $this->error('Unterminated string', $line, $column);
            }

            if ($firstByte === '"') {
                // End of string
                return new Token(TokenType::STRING, $result, $line, $column);
            }

            if ($firstByte === '\\') {
                // Escape sequence
                $result .= $this->parseEscapeSequence();

                continue;
            }

            // Validate control characters (0x00-0x1F are invalid)
            $ord = ord($firstByte);
            if ($ord < 0x20) {
                throw $this->error(
                    sprintf('Invalid control character in string (0x%02x)', $ord),
                    $this->buffer->getLine(),
                    $this->buffer->getColumn()
                );
            }

            // Read complete UTF-8 character
            $char = $this->readUtf8Character($firstByte);

            // Validate UTF-8
            if (! mb_check_encoding($char, 'UTF-8')) {
                throw $this->error(
                    'Invalid UTF-8 sequence in string',
                    $this->buffer->getLine(),
                    $this->buffer->getColumn()
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
            // 2-byte character: 110xxxxx 10xxxxxx
            $additionalBytes = 1;
        } elseif (($ord & 0xF0) === 0xE0) {
            // 3-byte character: 1110xxxx 10xxxxxx 10xxxxxx
            $additionalBytes = 2;
        } elseif (($ord & 0xF8) === 0xF0) {
            // 4-byte character: 11110xxx 10xxxxxx 10xxxxxx 10xxxxxx
            $additionalBytes = 3;
        } else {
            // Invalid UTF-8 start byte
            return $firstByte;
        }

        // Read additional bytes
        $char = $firstByte;
        for ($i = 0; $i < $additionalBytes; $i++) {
            $byte = $this->buffer->readByte();
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
        $char = $this->buffer->readByte();

        if ($char === null) {
            throw $this->error(
                'Unterminated escape sequence',
                $this->buffer->getLine(),
                $this->buffer->getColumn()
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
                $this->buffer->getLine(),
                $this->buffer->getColumn()
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
        $hex = $this->buffer->readChunk(4);

        if (strlen($hex) !== 4 || ! ctype_xdigit($hex)) {
            throw $this->error(
                "Invalid Unicode escape sequence: \\u$hex",
                $this->buffer->getLine(),
                $this->buffer->getColumn()
            );
        }

        $codepoint = hexdec($hex);

        // Handle UTF-16 surrogate pairs (high surrogate: 0xD800-0xDBFF)
        if ($codepoint >= 0xD800 && $codepoint <= 0xDBFF) {
            // Expect low surrogate
            if ($this->buffer->peek() === '\\' && $this->buffer->peek(1) === 'u') {
                $this->buffer->readByte(); // consume \
                $this->buffer->readByte(); // consume u

                $lowHex = $this->buffer->readChunk(4);

                if (strlen($lowHex) === 4 && ctype_xdigit($lowHex)) {
                    $lowCodepoint = hexdec($lowHex);

                    // Validate low surrogate (0xDC00-0xDFFF)
                    if ($lowCodepoint >= 0xDC00 && $lowCodepoint <= 0xDFFF) {
                        // Combine surrogates into single codepoint
                        $codepoint = 0x10000 + (($codepoint & 0x3FF) << 10) + ($lowCodepoint & 0x3FF);
                    }
                }
            }
        }

        // Convert codepoint to UTF-8
        return mb_chr((int) $codepoint, 'UTF-8');
    }

    /**
     * Scan number token (integer, float, or scientific notation)
     *
     * Parses numbers according to RFC 8259 and converts to appropriate PHP type.
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

        // Build number as integer until we hit decimal/exponent
        $intPart = 0;
        $fracPart = 0;
        $fracDigits = 0;
        $expPart = 0;
        $expNegative = false;

        // Handle negative sign
        if ($isNegative) {
            $firstChar = $this->buffer->readByte();
            if ($firstChar === null || ! ctype_digit($firstChar)) {
                throw $this->error('Expected digit after minus sign', $line, $column);
            }
        }

        // Integer part
        if ($firstChar === '0') {
            // Leading zero - next must be . or e/E or end
            $next = $this->buffer->peek();
            if ($next !== null && ctype_digit($next)) {
                throw $this->error('Leading zeros not allowed', $line, $column);
            }
            $intPart = 0;
        } else {
            // Parse integer digits
            $intPart = ord($firstChar) - ord('0');

            while (true) {
                $char = $this->buffer->peek();
                if ($char === null || ! ctype_digit($char)) {
                    break;
                }
                $this->buffer->readByte();
                $intPart = $intPart * 10 + (ord($char) - ord('0'));
            }
        }

        // Decimal part
        if ($this->buffer->peek() === '.') {
            $isFloat = true;
            $this->buffer->readByte(); // consume .

            $char = $this->buffer->peek();
            // @phpstan-ignore identical.alwaysFalse
            if ($char === null) {
                throw $this->error('Expected digit after decimal point', $line, $column);
            }
            // @phpstan-ignore function.impossibleType
            if (! ctype_digit($char)) {
                throw $this->error('Expected digit after decimal point', $line, $column);
            }

            while (true) {
                $char = $this->buffer->peek();
                // @phpstan-ignore identical.alwaysFalse
                if ($char === null) {
                    break;
                }
                // @phpstan-ignore function.impossibleType
                if (! ctype_digit($char)) {
                    break;
                }
                $this->buffer->readByte();
                $fracPart = $fracPart * 10 + (ord($char) - ord('0'));
                $fracDigits++;
            }
        }

        // Exponent part
        $next = $this->buffer->peek();
        if ($next === 'e' || $next === 'E') {
            $isFloat = true;
            $this->buffer->readByte(); // consume e/E

            $char = $this->buffer->peek();
            if ($char === '+' || $char === '-') {
                $expNegative = ($char === '-');
                $this->buffer->readByte();
                $char = $this->buffer->peek();
            }

            if ($char === null || ! ctype_digit($char)) {
                throw $this->error('Expected digit in exponent', $line, $column);
            }

            while (true) {
                $char = $this->buffer->peek();
                if ($char === null || ! ctype_digit($char)) {
                    break;
                }
                $this->buffer->readByte();
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

        // First character already consumed, read remaining
        $remaining = $this->buffer->readChunk($len - 1);

        if ($remaining !== substr($expected, 1)) {
            throw $this->error("Invalid keyword, expected '$expected'", $line, $column);
        }

        return new Token($type, $value, $line, $column);
    }

    /**
     * Create ParseException with position information
     *
     * @param  string  $message  Error message
     * @param  int  $line  Line number (0-based from buffer)
     * @param  int  $column  Column number (0-based from buffer)
     * @return ParseException Exception with position
     */
    private function error(string $message, int $line, int $column): ParseException
    {
        $exception = new ParseException($message);
        $exception->setPosition($line + 1, $column + 1); // Convert 0-based to 1-based

        return $exception;
    }
}
