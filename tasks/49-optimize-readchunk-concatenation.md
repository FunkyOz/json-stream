---
title: Optimize String Concatenation in BufferManager::readChunk()
status: todo
priority: Low
description: Replace string concatenation in loop with array implode for better performance
---

## Objectives
- Replace string concatenation in loop with array building and implode
- Improve performance for large chunk reads
- Reduce memory allocation overhead
- Benchmark performance improvement

## Deliverables
1. Optimized `readChunk()` implementation in BufferManager
2. Performance benchmarks comparing old vs new implementation
3. Tests verifying correctness of optimized version
4. Documentation of performance characteristics

## Technical Details

**Location:** `src/Internal/BufferManager.php:148-182`

**Current Issue:**
```php
while ($remaining > 0) {
    // ...
    $result .= $chunk;
    // String concatenation in PHP can be inefficient
    // Each concatenation may require reallocation and copying
    // ...
}
```

**Performance Impact:**
- String concatenation in loop causes memory reallocation
- For large chunks (many iterations), this is O(n²) in worst case
- Modern PHP optimizers may mitigate this, but array approach is safer

**Proposed Solution:**
```php
public function readChunk(int $size): string
{
    $chunks = [];
    $remaining = $size;

    while ($remaining > 0) {
        // Fill buffer if needed
        if ($this->bufferOffset >= $this->bufferLength) {
            $this->fillBuffer();

            if ($this->bufferLength === 0) {
                break; // End of stream
            }
        }

        // Calculate how much to read from buffer
        $availableInBuffer = $this->bufferLength - $this->bufferOffset;
        $toRead = min($remaining, $availableInBuffer);

        // Read from buffer
        $chunk = substr($this->buffer, $this->bufferOffset, $toRead);
        $chunks[] = $chunk;

        $this->bufferOffset += $toRead;
        $this->position += $toRead;
        $remaining -= $toRead;
    }

    return implode('', $chunks);
}
```

**Benchmarking:**
```php
// Test with various chunk sizes
$sizes = [100, 1000, 10000, 100000];

foreach ($sizes as $size) {
    // Old implementation
    $start = microtime(true);
    $result1 = $oldReadChunk($size);
    $time1 = microtime(true) - $start;

    // New implementation
    $start = microtime(true);
    $result2 = $newReadChunk($size);
    $time2 = microtime(true) - $start;

    $improvement = (($time1 - $time2) / $time1) * 100;
    echo "Size $size: {$improvement}% improvement\n";
}
```

## Dependencies
- None

## Estimated Complexity
**Low** - Simple refactoring with clear pattern

## Implementation Notes
- Modern PHP (8.1+) has optimized string concatenation, so improvement may be modest
- Main benefit is predictable performance (no worst-case quadratic behavior)
- `implode('')` is typically faster than repeated `.=` for >10 concatenations
- Memory usage should be similar or slightly better
- Consider threshold: use concatenation for small chunks, array for large

**Optimization Considerations:**
```php
// Hybrid approach - use concatenation for small reads
public function readChunk(int $size): string
{
    // For small reads, concatenation is fine
    if ($size < 1000) {
        return $this->readChunkSimple($size);
    }

    // For large reads, use array approach
    return $this->readChunkOptimized($size);
}
```

**Alternative: String Builder Pattern:**
```php
class StringBuilder
{
    private array $chunks = [];

    public function append(string $chunk): void
    {
        $this->chunks[] = $chunk;
    }

    public function toString(): string
    {
        return implode('', $this->chunks);
    }
}
```

**Testing:**
- Verify correctness: result should be identical to original
- Test edge cases: empty chunks, single byte reads, very large reads
- Test buffer boundary conditions
- Performance regression tests

## Acceptance Criteria
- [ ] Implementation uses array building instead of string concatenation
- [ ] Tests verify identical output to original implementation
- [ ] Tests cover edge cases (empty, small, large reads)
- [ ] Benchmark shows performance improvement (or no regression)
- [ ] Consider hybrid approach if benchmarks show small-read penalty
- [ ] Document performance characteristics in code comments
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
