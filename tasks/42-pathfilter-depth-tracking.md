---
title: Add Depth Tracking to PathFilter::walk()
status: todo
priority: Medium
description: Add depth tracking to recursive PathFilter::walk() to prevent stack overflow
---

## Objectives
- Add depth tracking to `PathFilter::walk()` method
- Respect the same depth limits enforced during parsing
- Prevent stack overflow from deeply nested data structures
- Consider converting to iterative traversal for better safety

## Deliverables
1. Modified `PathFilter::walk()` with depth tracking
2. Depth limit enforcement matching Parser's max depth
3. Alternative iterative implementation (optional but recommended)
4. Unit tests verifying depth limit enforcement
5. Tests for stack safety with deeply nested structures

## Technical Details

**Location:** `src/Internal/JsonPath/PathFilter.php:49-78`

**Current Issue:**
```php
private function walk(mixed $value, array &$results): void
{
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            // Recursive call without depth tracking
            $this->walk($item, $results);
        }
    }
}
```

**Impact:**
- Parser enforces depth limits during streaming
- But `readAllMatches()` buffers entire structure, bypassing streaming limits
- Subsequent `walk()` could overflow stack on deeply nested data

**Proposed Solution (Option 1: Add Depth Parameter):**
```php
class PathFilter
{
    private int $maxDepth;

    public function __construct(PathExpression $expression, int $maxDepth = 512)
    {
        $this->expression = $expression;
        $this->maxDepth = $maxDepth;
    }

    public function filter(mixed $value): array
    {
        $results = [];
        $this->walk($value, $results, 0);
        return $results;
    }

    private function walk(mixed $value, array &$results, int $depth): void
    {
        if ($depth > $this->maxDepth) {
            throw new ParseException(
                "Maximum depth of {$this->maxDepth} exceeded during path traversal"
            );
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->walkValue($key, $item, $results, $depth);
                $this->walk($item, $results, $depth + 1);
            }
        } elseif (is_object($value)) {
            foreach (get_object_vars($value) as $key => $item) {
                $this->walkValue($key, $item, $results, $depth);
                $this->walk($item, $results, $depth + 1);
            }
        }
    }
}
```

**Proposed Solution (Option 2: Iterative with Explicit Stack):**
```php
private function walk(mixed $value, array &$results): void
{
    // Use explicit stack to avoid recursion
    $stack = [[$value, 0]]; // [value, depth]

    while (!empty($stack)) {
        [$current, $depth] = array_pop($stack);

        if ($depth > $this->maxDepth) {
            throw new ParseException(
                "Maximum depth of {$this->maxDepth} exceeded during path traversal"
            );
        }

        if (is_array($current)) {
            foreach ($current as $key => $item) {
                $this->walkValue($key, $item, $results, $depth);
                $stack[] = [$item, $depth + 1];
            }
        } elseif (is_object($current)) {
            foreach (get_object_vars($current) as $key => $item) {
                $this->walkValue($key, $item, $results, $depth);
                $stack[] = [$item, $depth + 1];
            }
        }
    }
}

private function walkValue(string|int $key, mixed $item, array &$results, int $depth): void
{
    // Check if this value matches any path segments
    // (existing matching logic)
}
```

## Dependencies
- Should use the same maxDepth value from Parser/Config

## Estimated Complexity
**Medium** - Option 1 is straightforward; Option 2 requires refactoring

## Implementation Notes
- Default max depth should match Parser's default (typically 512)
- Iterative approach (Option 2) is safer but requires more refactoring
- Recursive approach (Option 1) is simpler but still has theoretical stack limits
- PHP's default stack size can handle ~1000 recursive calls
- Consider performance impact of depth tracking
- May need to pass maxDepth through constructor or method parameter

**Depth Limit Sources:**
- `StreamReader::withMaxDepth()` - configurable per reader
- `Config::MAX_DEPTH` - global default (if exists)
- Parser already enforces depth during streaming

**Test Cases:**
- Deeply nested arrays: `[[[[[[...]]]]]]`
- Deeply nested objects: `{"a":{"b":{"c":{...}}}}`
- Mixed nesting: `{"a":[{"b":[...]}]}`
- Exactly at limit (should work)
- One over limit (should throw)

## Acceptance Criteria
- [ ] Depth tracking is implemented in walk() method
- [ ] Depth limit matches Parser's maxDepth setting
- [ ] Exception is thrown when depth limit is exceeded
- [ ] Tests verify depth limit enforcement
- [ ] Tests verify correct behavior at exactly the limit
- [ ] Tests verify deeply nested structures don't cause stack overflow
- [ ] Consider implementing iterative version for better safety
- [ ] Documentation explains depth limit behavior
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
