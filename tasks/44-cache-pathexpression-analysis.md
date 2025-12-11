---
title: Cache PathExpression Analysis Results
status: todo
priority: Low
description: Cache results of PathExpression analysis methods to avoid repeated iteration
---

## Objectives
- Cache results of `hasRecursive()`, `canUseSimpleStreaming()`, `hasEarlyTermination()`
- Calculate analysis results once during construction
- Improve performance for repeated method calls
- Use readonly properties for immutability

## Deliverables
1. Modified `PathExpression` class with cached analysis results
2. Readonly properties for analysis flags
3. Performance benchmarks showing improvement (if measurable)
4. Unit tests verifying cached values are correct

## Technical Details

**Location:** `src/Internal/JsonPath/PathExpression.php`

**Current Implementation:**
```php
public function hasRecursive(): bool
{
    foreach ($this->segments as $segment) {
        if ($segment->isRecursive()) {
            return true;
        }
    }
    return false;
}

public function canUseSimpleStreaming(): bool
{
    foreach ($this->segments as $segment) {
        if ($segment->isRecursive() || $segment->requiresBuffering()) {
            return false;
        }
    }
    return true;
}

// Similar for other analysis methods...
```

**Issue:**
- Each method iterates through all segments
- If called multiple times, redundant iterations occur
- Methods are pure (always return same result for same segments)

**Proposed Solution:**
```php
class PathExpression
{
    /** @var array<PathSegment> */
    private readonly array $segments;

    private readonly string $originalPath;

    // Cached analysis results
    private readonly bool $hasRecursive;
    private readonly bool $canUseSimpleStreaming;
    private readonly bool $hasEarlyTermination;
    private readonly bool $hasNegativeIndices;

    public function __construct(string $originalPath, array $segments)
    {
        $this->originalPath = $originalPath;
        $this->segments = $segments;

        // Calculate analysis results once during construction
        $this->hasRecursive = $this->calculateHasRecursive();
        $this->canUseSimpleStreaming = $this->calculateCanUseSimpleStreaming();
        $this->hasEarlyTermination = $this->calculateHasEarlyTermination();
        $this->hasNegativeIndices = $this->calculateHasNegativeIndices();
    }

    public function hasRecursive(): bool
    {
        return $this->hasRecursive;
    }

    public function canUseSimpleStreaming(): bool
    {
        return $this->canUseSimpleStreaming;
    }

    public function hasEarlyTermination(): bool
    {
        return $this->hasEarlyTermination;
    }

    public function hasNegativeIndices(): bool
    {
        return $this->hasNegativeIndices;
    }

    private function calculateHasRecursive(): bool
    {
        foreach ($this->segments as $segment) {
            if ($segment->isRecursive()) {
                return true;
            }
        }
        return false;
    }

    private function calculateCanUseSimpleStreaming(): bool
    {
        foreach ($this->segments as $segment) {
            if ($segment->isRecursive() || $segment->requiresBuffering()) {
                return false;
            }
        }
        return true;
    }

    private function calculateHasEarlyTermination(): bool
    {
        // Implementation depends on current logic
        // ...
    }

    private function calculateHasNegativeIndices(): bool
    {
        foreach ($this->segments as $segment) {
            if ($segment instanceof ArraySliceSegment || $segment instanceof ArrayIndexSegment) {
                if ($segment->hasNegativeIndex()) {
                    return true;
                }
            }
        }
        return false;
    }
}
```

## Dependencies
- May relate to Task 39 (negative indices) if implementing `hasNegativeIndices()`

## Estimated Complexity
**Low** - Straightforward refactoring with clear benefits

## Implementation Notes
- `readonly` properties are available in PHP 8.1+
- Analysis methods become simple property accessors
- Constructor becomes slightly more complex but runs only once
- May need to add `hasNegativeIndex()` method to segment classes
- Consider whether all analysis should be in constructor or lazy-loaded
- Current implementation is likely not a performance bottleneck, but caching is good practice

**Benefits:**
- O(n) iteration happens once instead of repeatedly
- Immutable analysis results (cannot change after construction)
- Clearer intent (analysis is a construction-time concern)
- Easier to add new analysis methods in the future

**Considerations:**
- If PathExpression is short-lived and methods rarely called, benefit is minimal
- Memory overhead is negligible (few boolean flags)
- Code becomes slightly more complex (constructor does more work)

## Acceptance Criteria
- [ ] Analysis results are calculated once during construction
- [ ] Results are stored in readonly properties
- [ ] Public methods return cached values
- [ ] Tests verify all cached values are correct
- [ ] Tests verify values cannot be changed after construction
- [ ] Consider adding performance benchmark
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
