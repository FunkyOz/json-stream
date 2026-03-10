---
title: Merge BufferManager into Lexer for Hot Path Optimization
status: done
priority: Critical
description: Eliminate per-byte method call overhead by merging BufferManager into Lexer, removing all inter-object calls from the tokenization hot path
---

## Objectives
- Eliminate all inter-object method call overhead between Lexer and BufferManager
- Achieve measurable throughput improvement (target: 50-80% for pure tokenization)
- Simplify buffer boundary handling by consolidating state into a single class

## Deliverables
1. Merged Lexer class that owns stream I/O and buffer management directly
2. Removal of `BufferManager.php` as a standalone class
3. Updated `StreamReader` to construct Lexer with stream parameters directly
4. All existing tests passing with no regressions
5. Benchmarks showing before/after throughput improvement

## Technical Details

**Location:** `src/Internal/BufferManager.php`, `src/Internal/Lexer.php`, `src/Reader/StreamReader.php`

**Current Issue:**
Every byte in the JSON input requires 2-4 cross-object method calls:
- `peek()` to check what the next byte is
- `readByte()` to consume it
- `getLine()`/`getColumn()` for position tracking

Each call involves:
1. PHP method call overhead (~50-100ns per call)
2. Buffer bounds check (`$this->bufferPosition >= $this->bufferLength`)
3. Position tracking via `trackPosition()` (another method call)
4. `$this->totalBytesRead++` increment

For a 1MB file, this means ~2-4 million method calls just for byte access.

**Profiling Data:**
- Current throughput: ~6.19 MB/s (43.6x slower than json_decode)
- readByte/peek account for estimated 40-50% of total parse time

**Chosen Approach: Merge BufferManager into Lexer**

BufferManager has exactly one consumer (Lexer) — it is not a reusable utility. Both classes are `@internal final` with no external API stability concerns. Merging eliminates all inter-object overhead and simplifies the critical buffer-boundary logic by maintaining a single source of truth for position state.

This was chosen over the accessor approach (exposing `getBufferRef()`, `setBufferPosition()` etc.) because:
- Accessor approach still requires method calls for `ensureAvailable()`, `getBufferRef()` after refill
- Accessor approach creates an awkward quasi-public API on an internal class
- Accessor approach still requires two objects to stay in sync (source of Task 22 bug)
- Merge gives ~50-80% throughput improvement vs ~30-50% for accessors

### Step 1: Move BufferManager properties into Lexer

```php
final class Lexer
{
    // Former BufferManager state
    private string $buffer = '';
    private int $bufferPosition = 0;
    private int $bufferLength = 0;
    private int $totalBytesRead = 0;
    private bool $eof = false;
    private int $line = 0;
    private int $column = 0;

    // Lexer state
    private ?Token $peekedToken = null;

    /**
     * @param resource $stream Stream resource to read from
     * @param int $bufferSize Buffer size in bytes
     */
    public function __construct(
        private readonly mixed $stream,
        private readonly int $bufferSize = Config::DEFAULT_BUFFER_SIZE
    ) {
        // Stream validation (from BufferManager constructor)
        if (!is_resource($this->stream)) {
            throw new IOException('Invalid stream resource');
        }
        // ... mode and buffer size validation
    }
}
```

### Step 2: Inline hot-path byte access

Replace all `$this->buffer->readByte()` / `$this->buffer->peek()` calls with direct property access:

```php
private function skipWhitespace(): void
{
    while (true) {
        if ($this->bufferPosition >= $this->bufferLength) {
            if (!$this->refillBuffer()) {
                return;
            }
        }

        $ch = $this->buffer[$this->bufferPosition];
        if ($ch !== ' ' && $ch !== "\n" && $ch !== "\r" && $ch !== "\t") {
            return;
        }

        // Inline position tracking
        if ($ch === "\n") {
            $this->line++;
            $this->column = 0;
        } else {
            $this->column++;
        }
        $this->bufferPosition++;
        $this->totalBytesRead++;
    }
}
```

### Step 3: Inline readByte/peek as private methods

For non-hot-path code (unicode handling, escape sequences), keep `readByte()` and `peek()` as private methods to avoid code duplication:

```php
private function readByte(): ?string
{
    if ($this->bufferPosition >= $this->bufferLength) {
        if (!$this->refillBuffer()) {
            return null;
        }
    }

    $byte = $this->buffer[$this->bufferPosition++];
    $this->totalBytesRead++;

    if ($byte === "\n") {
        $this->line++;
        $this->column = 0;
    } else {
        $this->column++;
    }

    return $byte;
}

private function peek(int $offset = 0): ?string
{
    $pos = $this->bufferPosition + $offset;

    if ($pos < $this->bufferLength) {
        return $this->buffer[$pos];
    }

    if (!$this->eof) {
        $this->refillBuffer();
        $pos = $this->bufferPosition + $offset;

        if ($pos < $this->bufferLength) {
            return $this->buffer[$pos];
        }
    }

    return null;
}
```

### Step 4: Move refillBuffer and reset into Lexer

```php
private function refillBuffer(): bool
{
    // Identical logic, now a private method on Lexer
    if ($this->eof) {
        return false;
    }
    $data = fread($this->stream, max(1, $this->bufferSize));
    // ... same refill logic
}

public function reset(): void
{
    // Used by StreamReader for seekable streams
    // ... same reset logic
}
```

### Step 5: Update StreamReader

```php
// Before:
$this->buffer = new BufferManager($stream, $bufferSize);
$this->lexer = new Lexer($this->buffer);

// After:
$this->lexer = new Lexer($stream, $bufferSize);

// Reset calls:
// Before: $this->buffer->reset();
// After:  $this->lexer->reset();

// Position calls:
// Before: $this->buffer->getLine(), $this->buffer->getTotalBytesRead()
// After:  $this->lexer->getLine(), $this->lexer->getTotalBytesRead()
```

### Step 6: Delete BufferManager.php

Remove `src/Internal/BufferManager.php` entirely. Migrate any remaining BufferManager-specific tests to test through the Lexer.

## Dependencies
- None (foundational optimization)

## Estimated Complexity
**High** - Mechanical but large refactoring of two tightly-coupled classes into one, with extensive test migration

## Implementation Notes
- The `@internal` annotation on both classes means we can change their API freely
- Position tracking (line/column) must still work correctly for error messages
- Buffer refill at boundaries must be handled correctly (this was the source of a critical bug before - Task 22). Merging actually reduces this risk since there's one position variable, not two objects that must agree
- The merged Lexer will be ~500+ lines but can be organized with clear method grouping (I/O section, tokenization section)
- `readChunk()` is used by Lexer for unicode/keywords — becomes a private method
- `reset()` is used by StreamReader — remains public on Lexer
- `getLine()`, `getColumn()`, `getTotalBytesRead()`, `isEof()` — remain public on Lexer
- Benchmark with both small (1KB) and large (30MB) files to verify improvement across scales
- Parser requires no changes (depends only on Lexer's `nextToken`/`peekToken` API)

**Test Migration:**
- BufferManager unit tests → rewrite as Lexer I/O tests (or remove if covered by integration tests)
- All existing Lexer tests pass unchanged (same `nextToken`/`peekToken` API)
- All existing Parser tests pass unchanged
- Buffer boundary crossing still works correctly
- Position tracking remains accurate

## Acceptance Criteria
- [ ] `BufferManager.php` removed; all its logic absorbed into Lexer
- [ ] Lexer constructor accepts stream + bufferSize directly
- [ ] `StreamReader` updated to construct Lexer without BufferManager
- [ ] Hot-path methods (skipWhitespace, scanToken, scanString, scanNumber) use direct `$this->buffer[$this->bufferPosition]` access
- [ ] Buffer refill at boundaries still works correctly
- [ ] Line/column tracking remains accurate
- [ ] All existing tests pass (598+ tests)
- [ ] PHPStan analysis passes at max level
- [ ] 100% code coverage maintained
- [ ] Benchmark shows >= 40% throughput improvement
- [ ] No memory usage increase
