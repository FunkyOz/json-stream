---
title: Remove mb_check_encoding() from String Scanning Hot Path
status: todo
priority: Critical
description: Eliminate expensive mb_check_encoding() call on every UTF-8 character during string scanning
---

## Objectives
- Remove `mb_check_encoding()` from the per-character string scanning loop
- Replace with lightweight byte-level UTF-8 validation
- Achieve measurable string parsing throughput improvement

## Deliverables
1. Refactored `scanString()` / `readUtf8Character()` with inline UTF-8 validation
2. Benchmarks showing string parsing throughput improvement
3. All existing tests passing (especially UTF-8 and surrogate pair tests)

## Technical Details

**Location:** `src/Internal/Lexer.php:131-178` (scanString), `src/Internal/Lexer.php:186-221` (readUtf8Character)

**Current Issue:**
```php
// Called for EVERY character in EVERY string value
$char = $this->readUtf8Character($firstByte);

// mb_check_encoding is a C function but still expensive per-character
if (! mb_check_encoding($char, 'UTF-8')) {
    throw $this->error('Invalid UTF-8 sequence in string', ...);
}
```

`mb_check_encoding()` is called for every single character in every string. For JSON with many string values (typical), this is a major bottleneck. The function:
1. Has PHP function call overhead
2. Internally allocates and processes the encoding check
3. Is redundant for ASCII characters (the vast majority of JSON content)

**Profiling Data:**
- String throughput: 10.78 MB/s
- mb_check_encoding accounts for estimated 20-30% of string scanning time
- 95%+ of characters in typical JSON are ASCII (no validation needed)

**Proposed Solution:**
```php
private function scanString(int $line, int $column): Token
{
    $result = '';

    while (true) {
        $firstByte = $this->buffer->readByte();
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

        $ord = ord($firstByte);

        // Control characters (0x00-0x1F) are invalid
        if ($ord < 0x20) {
            throw $this->error(
                sprintf('Invalid control character in string (0x%02x)', $ord),
                $this->buffer->getLine(), $this->buffer->getColumn()
            );
        }

        // ASCII fast path - no validation needed (0x20-0x7F are always valid)
        if ($ord < 0x80) {
            $result .= $firstByte;
            continue;
        }

        // Multi-byte UTF-8: validate continuation bytes inline
        if (($ord & 0xE0) === 0xC0) {
            $expected = 1;
        } elseif (($ord & 0xF0) === 0xE0) {
            $expected = 2;
        } elseif (($ord & 0xF8) === 0xF0) {
            $expected = 3;
        } else {
            throw $this->error('Invalid UTF-8 start byte', ...);
        }

        $char = $firstByte;
        for ($i = 0; $i < $expected; $i++) {
            $byte = $this->buffer->readByte();
            if ($byte === null || (ord($byte) & 0xC0) !== 0x80) {
                throw $this->error('Invalid UTF-8 continuation byte', ...);
            }
            $char .= $byte;
        }

        $result .= $char;
    }
}
```

**Key Insight:** By validating continuation bytes (checking `(ord($byte) & 0xC0) === 0x80`), we achieve the same validation as `mb_check_encoding()` but without the function call overhead. The existing `readUtf8Character()` already reads the right number of bytes - we just need to validate continuation bytes instead of calling mb_check_encoding afterward.

## Dependencies
- Task 57 (readByte optimization) should be done first for maximum benefit, but this task is independent

## Estimated Complexity
**Medium** - Straightforward replacement of validation strategy

## Implementation Notes
- Must still reject overlong encodings (e.g., 0xC0 0x80 for NUL)
- Must still reject surrogates in raw UTF-8 (0xED 0xA0 0x80 through 0xED 0xBF 0xBF)
- Must still reject codepoints above U+10FFFF
- For completeness, can add a final `mb_check_encoding` call on the complete string result as a safety net (once per string, not per character)
- ASCII fast path (`$ord < 0x80`) is the critical optimization since 95%+ of JSON content is ASCII

**Test Cases:**
- All existing UTF-8 tests pass
- Surrogate pair tests pass (Task 38)
- Invalid UTF-8 sequences are still rejected
- Overlong encodings are rejected
- Control characters are still rejected

## Acceptance Criteria
- [ ] mb_check_encoding() removed from per-character loop
- [ ] ASCII characters (0x20-0x7F) take the fast path with zero validation overhead
- [ ] Multi-byte UTF-8 validated by checking continuation bytes
- [ ] Invalid UTF-8 sequences still produce proper error messages
- [ ] All existing tests pass (598+ tests)
- [ ] PHPStan analysis passes
- [ ] String parsing benchmark shows >= 20% throughput improvement
