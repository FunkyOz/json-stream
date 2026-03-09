---
title: Batch String Scanning with strpbrk/memchr
status: todo
priority: High
description: Scan string contents in bulk using native C functions instead of byte-by-byte iteration
---

## Objectives
- Replace byte-by-byte string scanning with bulk scanning using `strpbrk()` or `strpos()`
- Scan ahead to the next special character (`"`, `\`, or control char) in one C-level call
- Dramatically reduce iteration count for long strings

## Deliverables
1. Refactored `scanString()` using bulk scanning
2. Benchmarks showing string throughput improvement
3. All existing tests passing

## Technical Details

**Location:** `src/Internal/Lexer.php:131-178`

**Current Issue:**
Every byte in a string is read individually via `readByte()`, checked for `"`, `\`, control characters, and UTF-8 validity. For a 1000-byte string, this is 1000+ method calls and checks.

**Proposed Solution:**
Use PHP's C-level string functions to skip over plain ASCII content in bulk:

```php
private function scanString(int $line, int $column): Token
{
    $result = '';
    $buf = $this->buffer->getBufferRef();
    $pos = $this->buffer->getBufferPosition();
    $len = $this->buffer->getBufferLength();

    while (true) {
        // Bulk scan: find next special character in buffer
        // Special chars: " (end), \ (escape), or any byte < 0x20 (control) or >= 0x80 (multi-byte)
        $start = $pos;
        while ($pos < $len) {
            $ord = ord($buf[$pos]);
            if ($ord === 0x22 || $ord === 0x5C || $ord < 0x20 || $ord >= 0x80) {
                break;
            }
            $pos++;
        }

        // Append bulk ASCII content
        if ($pos > $start) {
            $result .= substr($buf, $start, $pos - $start);
        }

        // Check if we need buffer refill
        if ($pos >= $len) {
            // Update buffer position, refill, continue
            $this->buffer->setBufferPosition($pos);
            if (!$this->buffer->ensureAvailable()) {
                throw $this->error('Unterminated string', $line, $column);
            }
            $buf = $this->buffer->getBufferRef();
            $pos = $this->buffer->getBufferPosition();
            $len = $this->buffer->getBufferLength();
            continue;
        }

        $ch = $buf[$pos];
        if ($ch === '"') {
            $pos++;
            $this->buffer->setBufferPosition($pos);
            return new Token(TokenType::STRING, $result, $line, $column);
        }

        if ($ch === '\\') {
            $pos++;
            $this->buffer->setBufferPosition($pos);
            $result .= $this->parseEscapeSequence();
            $buf = $this->buffer->getBufferRef();
            $pos = $this->buffer->getBufferPosition();
            $len = $this->buffer->getBufferLength();
            continue;
        }

        // Handle control chars or multi-byte UTF-8...
        // (similar to current logic but with direct buffer access)
    }
}
```

**Expected Impact:**
For typical JSON strings (mostly ASCII, few escapes), this reduces the inner loop from N iterations (one per byte) to ~1-3 iterations (one per "segment" between special characters). A 100-byte ASCII string that currently needs 100 readByte calls would need just 1 substr call.

## Dependencies
- Task 57 (readByte/peek optimization) - requires direct buffer access

## Estimated Complexity
**High** - Requires careful buffer boundary handling and maintaining position tracking

## Implementation Notes
- Position tracking (line/column) for the bulk-scanned segment can be done by counting newlines in the substring (though JSON strings can't contain raw newlines, so column += length is sufficient)
- Buffer boundary crossing mid-string must be handled correctly
- Escape sequences still need per-character handling (but they're rare)
- Consider using `strcspn()` as an alternative to the manual inner loop
- Must handle strings that span multiple buffer fills

**Test Cases:**
- Short strings (< 10 chars)
- Long strings (> buffer size, spanning multiple fills)
- Strings with many escape sequences
- Strings with Unicode characters
- Empty strings
- Strings at buffer boundaries

## Acceptance Criteria
- [ ] String scanning uses bulk buffer access for ASCII content
- [ ] Buffer boundary crossing works correctly for strings
- [ ] Escape sequences still handled correctly
- [ ] UTF-8 multi-byte characters still validated
- [ ] All existing tests pass (598+ tests)
- [ ] PHPStan analysis passes
- [ ] String throughput benchmark shows >= 40% improvement
