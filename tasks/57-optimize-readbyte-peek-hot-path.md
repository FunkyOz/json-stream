---
title: Optimize readByte() and peek() Hot Path
status: todo
priority: Critical
description: Eliminate per-byte method call overhead in BufferManager by inlining buffer access in the Lexer
---

## Objectives
- Reduce per-byte overhead in the tokenization hot path
- Eliminate method call overhead for `readByte()` and `peek()` which are called for every single byte
- Achieve measurable throughput improvement (target: 2-3x for pure tokenization)

## Deliverables
1. Refactored Lexer that accesses buffer internals directly for hot-path operations
2. Benchmarks showing before/after throughput improvement
3. All existing tests passing with no regressions

## Technical Details

**Location:** `src/Internal/BufferManager.php:77-138`, `src/Internal/Lexer.php:71-98`

**Current Issue:**
Every byte in the JSON input requires two method calls minimum:
- `peek()` to check what the next byte is
- `readByte()` to consume it

Each call involves:
1. PHP function call overhead (~50-100ns per call)
2. Buffer bounds check (`$this->bufferPosition >= $this->bufferLength`)
3. Position tracking via `trackPosition()` (another method call)
4. `$this->totalBytesRead++` increment

For a 1MB file, this means ~2-4 million method calls just for byte access.

**Profiling Data:**
- Current throughput: ~6.19 MB/s (43.6x slower than json_decode)
- readByte/peek account for estimated 40-50% of total parse time

**Proposed Solution:**
Expose buffer internals to the Lexer so it can directly access the buffer string and position:

```php
// BufferManager adds package-internal accessors
final class BufferManager
{
    // Expose for Lexer hot-path access
    public function getBufferRef(): string { return $this->buffer; }
    public function getBufferPosition(): int { return $this->bufferPosition; }
    public function getBufferLength(): int { return $this->bufferLength; }
    public function setBufferPosition(int $pos): void { $this->bufferPosition = $pos; }
    public function ensureAvailable(int $bytes = 1): bool { /* refill if needed */ }
}

// Lexer scanToken hot path
private function scanToken(): Token
{
    // Direct buffer access instead of peek()/readByte()
    $pos = $this->buffer->getBufferPosition();
    $len = $this->buffer->getBufferLength();

    // Skip whitespace inline
    $buf = $this->buffer->getBufferRef();
    while ($pos < $len) {
        $ch = $buf[$pos];
        if ($ch !== ' ' && $ch !== "\n" && $ch !== "\r" && $ch !== "\t") {
            break;
        }
        // Track position inline
        if ($ch === "\n") { $line++; $col = 0; } else { $col++; }
        $pos++;
    }

    // Need more data?
    if ($pos >= $len) {
        $this->buffer->setBufferPosition($pos);
        if (!$this->buffer->ensureAvailable()) {
            return new Token(TokenType::EOF, null, $line, $col);
        }
        // Re-fetch after refill
        $pos = $this->buffer->getBufferPosition();
        $buf = $this->buffer->getBufferRef();
        $len = $this->buffer->getBufferLength();
    }
    // ... continue with direct $buf[$pos] access
}
```

**Alternative Approach:**
Make Lexer a "friend" of BufferManager by merging them into a single class, eliminating all inter-object calls. This is more invasive but gives maximum performance.

## Dependencies
- None (foundational optimization)

## Estimated Complexity
**High** - Requires careful refactoring of two tightly-coupled classes while maintaining all behavior

## Implementation Notes
- The `@internal` annotation on both classes means we can change their API freely
- Position tracking (line/column) must still work correctly for error messages
- Buffer refill at boundaries must be handled correctly (this was the source of a critical bug before - Task 22)
- Consider making buffer, position, and length properties `public` on BufferManager since both classes are `@internal`
- Benchmark with both small (1KB) and large (30MB) files to verify improvement across scales

**Test Cases:**
- All existing Lexer tests pass unchanged
- All existing Parser tests pass unchanged
- Buffer boundary crossing still works correctly
- Position tracking remains accurate
- Performance benchmark shows measurable improvement

## Acceptance Criteria
- [ ] readByte()/peek() calls eliminated from Lexer hot path
- [ ] Direct buffer string access used in scanToken, skipWhitespace, scanString, scanNumber
- [ ] Buffer refill at boundaries still works correctly
- [ ] Line/column tracking remains accurate
- [ ] All existing tests pass (598+ tests)
- [ ] PHPStan analysis passes
- [ ] Benchmark shows >= 30% throughput improvement
- [ ] No memory usage increase
