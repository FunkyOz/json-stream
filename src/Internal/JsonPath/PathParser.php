<?php

declare(strict_types=1);

namespace JsonStream\Internal\JsonPath;

use JsonStream\Exception\PathException;

/**
 * Parses JSONPath expressions into PathExpression objects
 *
 * Supports:
 * - Root: $
 * - Child: .property or ['property']
 * - Recursive: ..property or ..[*]
 * - Array index: [0], [-1]
 * - Array slice: [0:5], [::2]
 * - Wildcard: [*] or .*
 * - Filter: [?(@.property op value)]
 *
 * @internal
 */
final class PathParser
{
    private string $path;

    private int $position = 0;

    private int $length = 0;

    /**
     * Parse a JSONPath expression
     *
     * @param  string  $path  JSONPath expression
     * @return PathExpression Parsed expression
     *
     * @throws PathException If path is invalid
     */
    public function parse(string $path): PathExpression
    {
        $this->path = $path;
        $this->position = 0;
        $this->length = strlen($path);

        if ($this->length === 0) {
            throw $this->createException('Empty JSONPath expression');
        }

        $segments = [];

        // Parse root
        if (! $this->consume('$')) {
            throw $this->createException('JSONPath must start with $');
        }

        $segments[] = new RootSegment();

        // Parse remaining segments
        while (! $this->isAtEnd()) {
            $this->skipWhitespace();

            if ($this->isAtEnd()) {
                break;
            }

            $segments[] = $this->parseSegment();
        }

        return new PathExpression($path, $segments);
    }

    /**
     * Parse a single path segment
     *
     * @throws PathException
     */
    private function parseSegment(): PathSegment
    {
        $this->skipWhitespace();

        // Check for recursive descent (..)
        $recursive = false;
        if ($this->peek() === '.' && $this->peek(1) === '.') {
            $this->advance(2);
            $recursive = true;
        } elseif ($this->peek() === '.') {
            $this->advance();
        }

        $this->skipWhitespace();

        // Bracket notation
        if ($this->peek() === '[') {
            return $this->parseBracketSegment($recursive);
        }

        // Dot notation for wildcard
        if ($this->peek() === '*') {
            $this->advance();

            return new WildcardSegment($recursive);
        }

        // Dot notation for property
        return $this->parsePropertySegment($recursive);
    }

    /**
     * Parse bracket notation segment [...]
     *
     * @throws PathException
     */
    private function parseBracketSegment(bool $recursive): PathSegment
    {
        $this->consume('[');
        $this->skipWhitespace();

        // Wildcard [*]
        if ($this->peek() === '*') {
            $this->advance();
            $this->skipWhitespace();
            $this->expect(']');

            return new WildcardSegment($recursive);
        }

        // Filter expression [?(...)]
        if ($this->peek() === '?') {
            return $this->parseFilterSegment();
        }

        // String key ['property']
        if ($this->peek() === '"' || $this->peek() === "'") {
            $property = $this->parseQuotedString();
            $this->skipWhitespace();
            $this->expect(']');

            return new PropertySegment($property, $recursive);
        }

        // Number or slice
        return $this->parseArraySegment($recursive);
    }

    /**
     * Parse array index or slice segment
     *
     * @throws PathException
     */
    private function parseArraySegment(bool $recursive): PathSegment
    {
        // Check if we have a colon first (for slices like [::2])
        if ($this->peek() === ':') {
            return $this->parseSliceSegment(null);
        }

        $start = $this->parseInteger();
        $this->skipWhitespace();

        // Check for slice
        if ($this->peek() === ':') {
            return $this->parseSliceSegment($start);
        }

        // Single index
        $this->expect(']');

        return new ArrayIndexSegment($start);
    }

    /**
     * Parse slice segment [start:end:step]
     *
     * @param  int|null  $start  Start index already parsed
     *
     * @throws PathException
     */
    private function parseSliceSegment(?int $start): PathSegment
    {
        $this->consume(':');
        $this->skipWhitespace();

        $end = null;
        if ($this->peek() !== ':' && $this->peek() !== ']') {
            $end = $this->parseInteger();
            $this->skipWhitespace();
        }

        $step = 1;
        if ($this->peek() === ':') {
            $this->advance();
            $this->skipWhitespace();

            if ($this->peek() !== ']') {
                $step = $this->parseInteger();
                $this->skipWhitespace();
            }
        }

        $this->expect(']');

        return new ArraySliceSegment($start, $end, $step);
    }

    /**
     * Parse filter expression [?(expression)]
     *
     * @throws PathException
     */
    private function parseFilterSegment(): PathSegment
    {
        $this->consume('?');
        $this->skipWhitespace();
        $this->expect('(');

        // Read until closing paren
        $expression = '';
        $depth = 1;

        // @phpstan-ignore greater.alwaysTrue
        while (! $this->isAtEnd() && $depth > 0) {
            $char = $this->peek();

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }

            $expression .= $char;
            $this->advance();
        }

        $this->expect(')');
        $this->skipWhitespace();
        $this->expect(']');

        return new FilterSegment(trim($expression));
    }

    /**
     * Parse property name in dot notation
     *
     * @throws PathException
     */
    private function parsePropertySegment(bool $recursive): PathSegment
    {
        $property = '';

        while (! $this->isAtEnd()) {
            $char = $this->peek();

            // Property name: alphanumeric, underscore, hyphen
            if (ctype_alnum($char) || $char === '_' || $char === '-') {
                $property .= $char;
                $this->advance();
            } else {
                break;
            }
        }

        if ($property === '') {
            throw $this->createException('Expected property name');
        }

        return new PropertySegment($property, $recursive);
    }

    /**
     * Parse a quoted string
     *
     * @throws PathException
     */
    private function parseQuotedString(): string
    {
        $quote = $this->peek();
        $this->advance(); // consume opening quote

        $string = '';

        while (! $this->isAtEnd()) {
            $char = $this->peek();

            if ($char === $quote) {
                $this->advance(); // consume closing quote

                return $string;
            }

            if ($char === '\\' && $this->peek(1) === $quote) {
                $this->advance(); // skip escape
                $string .= $quote;
                $this->advance();
            } else {
                $string .= $char;
                $this->advance();
            }
        }

        throw $this->createException('Unterminated string');
    }

    /**
     * Parse an integer (including negative)
     *
     * @throws PathException
     */
    private function parseInteger(): int
    {
        $number = '';

        if ($this->peek() === '-') {
            $number .= '-';
            $this->advance();
        }

        while (! $this->isAtEnd() && ctype_digit($this->peek())) {
            $number .= $this->peek();
            $this->advance();
        }

        if ($number === '' || $number === '-') {
            throw $this->createException('Expected integer');
        }

        return (int) $number;
    }

    /**
     * Peek at character at current position + offset
     */
    private function peek(int $offset = 0): ?string
    {
        $pos = $this->position + $offset;

        return $pos < $this->length ? $this->path[$pos] : null;
    }

    /**
     * Advance position by n characters
     */
    private function advance(int $count = 1): void
    {
        $this->position += $count;
    }

    /**
     * Check if at end of path
     */
    private function isAtEnd(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * Skip whitespace characters
     */
    private function skipWhitespace(): void
    {
        while (! $this->isAtEnd() && ctype_space($this->peek() ?? '')) {
            $this->advance();
        }
    }

    /**
     * Consume expected character
     *
     * @throws PathException
     */
    private function consume(string $expected): bool
    {
        if ($this->peek() === $expected) {
            $this->advance();

            return true;
        }

        return false;
    }

    /**
     * Expect character or throw exception
     *
     * @throws PathException
     */
    private function expect(string $expected): void
    {
        if (! $this->consume($expected)) {
            throw $this->createException("Expected '{$expected}'");
        }
    }

    /**
     * Create PathException with current context
     */
    private function createException(string $message): PathException
    {
        $context = $this->getContext();
        $exception = new PathException("{$message} at position {$this->position}\n{$context}");
        $exception->setPath($this->path);

        return $exception;
    }

    /**
     * Get context string showing current position
     */
    private function getContext(): string
    {
        $start = max(0, $this->position - 10);
        $end = min($this->length, $this->position + 10);

        $context = substr($this->path, $start, $end - $start);
        $pointer = str_repeat(' ', $this->position - $start).'^';

        return $context."\n".$pointer;
    }
}
