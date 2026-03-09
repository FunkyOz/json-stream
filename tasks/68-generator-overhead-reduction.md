---
title: Reduce Generator Yield Overhead
status: todo
priority: Low
description: Investigate and reduce the overhead of PHP generators in the streaming pipeline
---

## Objectives
- Measure the actual cost of generator `yield` in the parsing pipeline
- Explore alternatives for the internal iteration pattern
- Reduce overhead if measurable

## Deliverables
1. Benchmark comparing generator vs callback vs direct return patterns
2. Refactored internal iteration if generators prove costly
3. Analysis report if generators are not the bottleneck

## Technical Details

**Location:** `src/Internal/Parser.php` (parseArray, parseObject, parseAndExtractMatches)

**Current Issue:**
The Parser uses PHP generators (`yield`) to stream values:
```php
public function parseArray(): \Generator
{
    // ...
    while (true) {
        yield $this->parseValue();  // Generator yield per element
        // ...
    }
}
```

Generator overhead per yield includes:
1. Saving/restoring execution context
2. Value boxing for yield
3. Generator state machine transition

**Investigation Questions:**
1. What is the actual per-yield overhead in PHP 8.x? (Estimated ~1-2us)
2. How does it compare to callback-based iteration?
3. Is the overhead significant relative to parsing time per element?

**Potential Alternatives:**

### Callback-based iteration
```php
public function parseArrayWithCallback(callable $callback): void
{
    $this->expectToken(TokenType::LEFT_BRACKET);
    $this->increaseDepth();
    // ...
    while (true) {
        $callback($this->parseValue());
        // ...
    }
}
```

### Direct array return for small structures
```php
public function parseArrayDirect(int $maxSize = 1000): array
{
    // Parse directly into array without generator
    // Fall back to generator for large arrays
}
```

### Chunked iteration
```php
public function parseArrayChunked(int $chunkSize = 100): \Generator
{
    $chunk = [];
    while (true) {
        $chunk[] = $this->parseValue();
        if (count($chunk) >= $chunkSize) {
            yield $chunk;
            $chunk = [];
        }
    }
    if (!empty($chunk)) yield $chunk;
}
```

## Dependencies
- Task 67 (benchmark suite) for accurate measurement

## Estimated Complexity
**Medium** - Investigation + potential API changes

## Implementation Notes
- PHP 8.x has improved generator performance significantly
- The overhead may be negligible compared to I/O and parsing costs
- Changing from generators would break the public API (iterators depend on generators)
- Consider this a research task: measure first, optimize only if warranted
- The callback approach avoids generator overhead but is less ergonomic
- Chunked iteration reduces yield count but adds batching complexity

## Acceptance Criteria
- [ ] Benchmark comparing generator vs callback overhead per element
- [ ] Analysis of whether generator overhead is significant (> 5% of total time)
- [ ] If significant: implementation of alternative with measurable improvement
- [ ] If not significant: documented analysis explaining why generators are fine
- [ ] All existing tests pass
- [ ] Public API unchanged (iterators still work)
