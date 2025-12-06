<?php

declare(strict_types=1);

namespace JsonStream\Internal;

/**
 * Represents a single JSON token with position information
 *
 * Immutable value object containing token type, value, and position
 * for error reporting.
 *
 * @internal
 */
final class Token
{
    /**
     * @param  TokenType  $type  Token type
     * @param  mixed  $value  Token value (for STRING, NUMBER, TRUE, FALSE, NULL)
     * @param  int  $line  Line number (0-based, add 1 when displaying to user)
     * @param  int  $column  Column number (0-based, add 1 when displaying to user)
     */
    public function __construct(
        public readonly TokenType $type,
        public readonly mixed $value,
        public readonly int $line,
        public readonly int $column
    ) {
    }
}
