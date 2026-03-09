---
title: Optimize Whitespace Skipping
status: todo
priority: Medium
description: Skip whitespace in bulk using strspn() or direct buffer scanning instead of byte-by-byte
---

## Objectives
- Replace byte-by-byte whitespace skipping with bulk scanning
- Use C-level `strspn()` to advance past whitespace in a single call
- Reduce overhead for pretty-printed JSON with significant whitespace

## Deliverables
1. Refactored `skipWhitespace()` using `strspn()` or equivalent
2. Benchmarks showing improvement for pretty-printed JSON

## Technical Details

**Location:** `src/Internal/Lexer.php:103-120`

**Current Issue:**
```php
private function skipWhitespace(): void
{
    while (true) {
        $char = $this->buffer->peek();        // method call
        if ($char === null) return;
        if ($char === ' ' || $char === "\n" || $char === "\r" || $char === "\t") {
            $this->buffer->readByte();          // method call
            continue;
        }
        return;
    }
}
```

Each whitespace byte requires a `peek()` + `readByte()` call pair. For pretty-printed JSON, whitespace can be 30-50% of the file.

**Proposed Solution:**
```php
private function skipWhitespace(): void
{
    $buf = $this->buffer->getBufferRef();
    $pos = $this->buffer->getBufferPosition();
    $len = $this->buffer->getBufferLength();

    while (true) {
        // Skip whitespace bytes in bulk
        $skip = strspn($buf, " \n\r\t", $pos, $len - $pos);

        // Update position tracking for skipped bytes
        if ($skip > 0) {
            // Count newlines for line tracking
            $skipped = substr($buf, $pos, $skip);
            $newlines = substr_count($skipped, "\n");
            if ($newlines > 0) {
                $this->line += $newlines;
                $lastNewline = strrpos($skipped, "\n");
                $this->column = $skip - $lastNewline - 1;
            } else {
                $this->column += $skip;
            }
            $pos += $skip;
            $this->totalBytesRead += $skip;
        }

        if ($pos < $len) {
            $this->buffer->setBufferPosition($pos);
            return; // Non-whitespace found
        }

        // Buffer exhausted, try refill
        $this->buffer->setBufferPosition($pos);
        if (!$this->buffer->ensureAvailable()) {
            return; // EOF
        }
        $buf = $this->buffer->getBufferRef();
        $pos = $this->buffer->getBufferPosition();
        $len = $this->buffer->getBufferLength();
    }
}
```

## Dependencies
- Task 57 (buffer optimization) for direct buffer access

## Estimated Complexity
**Low** - Straightforward replacement with bulk operation

## Implementation Notes
- `strspn()` is a C-level function, very fast for this use case
- Position tracking (line/column) adds complexity; for minified JSON this optimization matters less
- Consider tracking newlines lazily or only on error paths
- For minified JSON, whitespace is minimal so impact is lower
- Benchmark with both minified and pretty-printed JSON

## Acceptance Criteria
- [ ] Whitespace skipped in bulk using strspn() or equivalent
- [ ] Line/column tracking remains accurate
- [ ] Works correctly at buffer boundaries
- [ ] All existing tests pass
- [ ] PHPStan analysis passes
- [ ] Pretty-printed JSON benchmark shows >= 15% improvement
