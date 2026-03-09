---
title: Lazy Position Tracking
status: todo
priority: Medium
description: Defer line/column tracking to error paths only, eliminating per-byte overhead in the normal case
---

## Objectives
- Remove per-byte `trackPosition()` calls from the happy path
- Compute line/column only when an error occurs (by scanning back through consumed data)
- Eliminate the branch and increment overhead for every byte read

## Deliverables
1. Refactored BufferManager with lazy position tracking
2. Error-path position computation
3. All existing tests passing with accurate error positions

## Technical Details

**Location:** `src/Internal/BufferManager.php:296-304`

**Current Issue:**
```php
private function trackPosition(string $byte): void
{
    if ($byte === "\n") {     // Branch per byte
        $this->line++;
        $this->column = 0;
    } else {
        $this->column++;      // Increment per byte
    }
}
```

This is called for every byte read via `readByte()` and `readChunk()`. For 1MB of JSON, that's ~1 million branch + increment operations. The line/column values are only used in error messages, which occur rarely.

**Proposed Solution:**

### Option A: Track byte offset only, compute line/column on demand
```php
final class BufferManager
{
    // Remove: private int $line = 0;
    // Remove: private int $column = 0;
    private int $byteOffset = 0;

    // Keep a sliding window of recently consumed data for error context
    private string $recentData = '';
    private int $recentDataStart = 0;

    public function readByte(): ?string
    {
        if ($this->bufferPosition >= $this->bufferLength) {
            if (!$this->refillBuffer()) return null;
        }
        $byte = $this->buffer[$this->bufferPosition++];
        $this->byteOffset++;
        // NO trackPosition call!
        return $byte;
    }

    public function getLine(): int
    {
        // Compute on demand by counting newlines in consumed data
        return $this->computeLineFromOffset($this->byteOffset);
    }

    public function getColumn(): int
    {
        return $this->computeColumnFromOffset($this->byteOffset);
    }
}
```

### Option B: Track position in Lexer only at token boundaries
```php
// Lexer tracks position only when starting a new token
private function scanToken(): Token
{
    $this->skipWhitespace();
    $this->tokenStartOffset = $this->buffer->getByteOffset();
    // ... scan token ...
    // Position computed only if error thrown
}
```

### Option C: Minimal tracking - count newlines only
Since JSON strings can't contain raw newlines, the only place newlines appear is between tokens (whitespace). Track line count in `skipWhitespace()` only:

```php
private function skipWhitespace(): void
{
    while (...) {
        if ($char === "\n") { $this->line++; $this->column = 0; }
        else { $this->column++; }
        // ... only for whitespace bytes, not for token content
    }
}
```

## Dependencies
- Task 57 (buffer optimization) for holistic buffer redesign

## Estimated Complexity
**Medium** - Requires changing how errors get position info across Lexer and Parser

## Implementation Notes
- Option C is the simplest and covers 90% of cases since newlines only appear in whitespace
- For Option A, need to keep enough data to compute position (or re-read from stream if seekable)
- Error positions don't need to be byte-perfect; being within a few characters is usually sufficient
- The Lexer already captures position at token start, so token-level tracking may be sufficient
- Must still pass all error position tests

**Test Cases:**
- Error messages still report correct (or close) line/column
- Multi-line JSON files report correct line numbers
- Single-line minified JSON reports correct column
- Errors at buffer boundaries report reasonable positions

## Acceptance Criteria
- [ ] Per-byte trackPosition() calls eliminated from hot path
- [ ] Error messages still report useful position information
- [ ] All existing tests pass (598+ tests)
- [ ] PHPStan analysis passes
- [ ] Benchmark shows >= 10% throughput improvement
