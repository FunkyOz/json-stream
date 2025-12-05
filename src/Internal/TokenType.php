<?php

declare(strict_types=1);

namespace JsonStream\Internal;

/**
 * JSON token types (RFC 8259 compliant)
 *
 * Represents all possible token types in valid JSON.
 *
 * @internal
 */
enum TokenType
{
    case LEFT_BRACE;      // {
    case RIGHT_BRACE;     // }
    case LEFT_BRACKET;    // [
    case RIGHT_BRACKET;   // ]
    case COLON;           // :
    case COMMA;           // ,
    case STRING;          // "..."
    case NUMBER;          // 123, -45.6, 7.8e9
    case TRUE;            // true
    case FALSE;           // false
    case NULL;            // null
    case EOF;             // End of file
}
