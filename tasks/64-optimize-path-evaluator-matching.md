---
title: Optimize PathEvaluator Matching Performance
status: todo
priority: Medium
description: Reduce overhead of path matching by caching depth, pre-computing segment count, and avoiding recursive calls
---

## Objectives
- Eliminate repeated `count($this->pathStack)` and `count($segments)` calls in PathEvaluator
- Convert recursive `matchSegments()` to iterative implementation
- Pre-compute and cache segment metadata for fast matching

## Deliverables
1. Optimized PathEvaluator with cached counts and iterative matching
2. Benchmarks showing JSONPath evaluation improvement

## Technical Details

**Location:** `src/Internal/JsonPath/PathEvaluator.php`

**Current Issue:**
The `matches()` method is called for every element during streaming. Each call:
1. Calls `$this->expression->getSegments()` (returns array, but involves method call)
2. Calls `count($this->pathStack)` (O(1) but still a function call)
3. Calls `count($segments)` (O(1) but still a function call)
4. Calls recursive `matchSegments()` which itself calls `count()` at every recursion level

```php
public function matches(): bool
{
    $segments = $this->expression->getSegments();  // method call
    $depth = count($this->pathStack);               // function call
    if (count($segments) === 1) {                   // function call
        return $depth === 0;
    }
    return $this->matchSegments($segments, 1, 0);   // recursive calls
}
```

**Proposed Solution:**

### 1. Cache segment array and count
```php
final class PathEvaluator
{
    /** @var PathSegment[] Cached segments */
    private array $segments;
    private int $segmentCount;
    private int $depth = 0;  // Track depth as int instead of count()

    public function __construct(private readonly PathExpression $expression)
    {
        $this->segments = $expression->getSegments();
        $this->segmentCount = count($this->segments);
    }

    public function enterLevel(string|int $key, mixed $value): void
    {
        $this->pathStack[] = $key;
        $this->valueStack[] = $value;
        $this->depth++;
    }

    public function exitLevel(): void
    {
        array_pop($this->pathStack);
        array_pop($this->valueStack);
        $this->depth--;
    }
}
```

### 2. Iterative matchSegments for non-recursive paths
```php
public function matches(): bool
{
    if ($this->segmentCount === 1) {
        return $this->depth === 0;
    }

    // Fast path for non-recursive expressions (most common)
    if (!$this->expression->hasRecursive()) {
        // Simple depth check + segment matching
        $expectedDepth = $this->segmentCount - 1;
        if ($this->depth !== $expectedDepth) {
            return false;
        }

        for ($i = 0; $i < $this->depth; $i++) {
            if (!$this->segments[$i + 1]->matches(
                $this->pathStack[$i],
                $this->valueStack[$i],
                $i
            )) {
                return false;
            }
        }
        return true;
    }

    // Recursive path: use existing logic
    return $this->matchSegments($this->segments, 1, 0);
}
```

### 3. Early depth rejection
```php
// If depth doesn't match expected, skip matching entirely
if ($this->depth > $this->segmentCount - 1) {
    return false; // Too deep for non-recursive paths
}
```

## Dependencies
- Task 44 (PathExpression caching) - already done, provides `hasRecursive()` cache

## Estimated Complexity
**Medium** - Logic refactoring with cached state

## Implementation Notes
- The `hasRecursive()` method is already cached from Task 44
- Non-recursive paths are the common case ($.prop, $.arr[*], $.a.b.c)
- The iterative fast path avoids PHP's recursive function call overhead
- Must handle `reset()` to clear the cached depth counter
- `count($this->pathStack)` is O(1) in PHP but still has function call overhead vs direct int

**Test Cases:**
- Simple property access: $.name
- Array wildcard: $.items[*]
- Deep property: $.a.b.c.d
- Recursive descent: $..name
- Filter expressions: $.items[?(@.price > 10)]
- All existing JSONPath tests

## Acceptance Criteria
- [ ] Segment array and count cached at construction
- [ ] Depth tracked as integer (no count() calls)
- [ ] Non-recursive paths use iterative matching
- [ ] Recursive paths still work correctly
- [ ] All existing tests pass (598+ tests)
- [ ] PHPStan analysis passes
- [ ] JSONPath benchmark shows measurable improvement
