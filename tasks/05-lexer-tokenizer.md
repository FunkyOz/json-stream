---
title: Lexer/Tokenizer
status: done
priority: Critical
description: Implement a JSON lexer that converts a byte stream into tokens (e.g., {, }, [, ], strings, numbers, booleans, null).
---

## Objectives
- Create Lexer class that produces JSON tokens
- Handle all JSON token types
- Track position for error reporting
- Validate JSON syntax at token level
- Optimize for streaming (minimal lookahead)

## Deliverables
1. `src/Internal/Token.php` - Token class
   - Token type enum/constants (OBJECT_START, OBJECT_END, ARRAY_START, ARRAY_END, STRING, NUMBER, BOOLEAN, NULL, COLON, COMMA, EOF)
   - Token value
   - Position (line, column)

2. `src/Internal/Lexer.php` - Lexer implementation
   - Constructor accepting BufferManager
   - `nextToken(): Token` - Get next token
   - `peekToken(): Token` - Look ahead without consuming
   - Handle whitespace
   - Parse strings (with escape sequences)
   - Parse numbers (int, float, scientific notation)
   - Parse keywords (true, false, null)
   - Track line/column positions

## Technical Considerations
- Proper Unicode handling (UTF-8)
- Escape sequence handling (\n, \t, \", \\, \/, \uXXXX)
- Number validation (no leading zeros except 0.x)
- Error reporting with precise positions
- Streaming-friendly (no backtracking)

## API Compliance
- Must handle all valid JSON per RFC 8259
- Strict mode validation

## Dependencies
- Task 03: Exception Classes (for ParseException)
- Task 04: Buffer Manager

## Estimated Complexity
**High** - Complex parsing logic

## Acceptance Criteria
- [x] Token class implemented
- [x] Lexer class implemented
- [x] Handles all JSON token types
- [x] Proper escape sequence handling
- [x] Accurate position tracking
- [x] Comprehensive unit tests
- [x] Handles edge cases (very long strings, large numbers)
- [x] Performance tests pass
