---
title: Nested Wildcard Streaming Support
status: todo
priority: Low
description: Implement true streaming for nested wildcard patterns like $.users[*].posts[*] to avoid PathFilter memory buffering
---

## Objectives
- Implement true streaming for nested wildcard patterns (e.g., `$.users[*].posts[*]`)
- Enable O(element) memory usage for patterns with multiple wildcards
- Remove the PathFilter fallback requirement for nested wildcard patterns
- Maintain backward compatibility with existing JSONPath behavior

## Background

Task 25 implemented complex pattern streaming with the following results:
- **Property-after-wildcard** (`$.users[*].name`): ✅ Implemented with O(element) memory
- **Filter expressions** (`$.users[?(@.age > 18)]`): ✅ Implemented with streaming
- **Nested wildcards** (`$.users[*].posts[*]`): ❌ **Deferred** - still uses PathFilter fallback

The current implementation in `PathExpression::canUseSimpleStreaming()` returns `false` for patterns with multiple wildcards:

```php
// Don't stream if:
// - Multiple wildcards (nested wildcard streaming not yet implemented)
if ($wildcardCount > 1) {
    return false;
}
```

This causes patterns like `$.users[*].posts[*]` to fall back to `PathFilter`, which buffers the entire JSON structure into memory before filtering.

## Deliverables

1. **Recursive streaming implementation**
   - Stream outer array elements progressively
   - For each outer element, stream inner array elements
   - Yield all nested matches without buffering entire structure

2. **Updated pattern classification**
   - Modify `canUseSimpleStreaming()` to return `true` for nested wildcards
   - Add detection for nested wildcard depth (2-level, 3-level, etc.)

3. **Memory-efficient parsing**
   - Parse outer elements one at a time
   - Extract and stream inner elements from each outer element
   - Discard outer element after processing its inner elements

4. **Comprehensive tests**
   - Memory tests confirming O(element) usage
   - Correctness tests comparing with PathFilter results
   - Edge cases with deeply nested structures

## Technical Details

### Current Behavior

```php
// Pattern: $.users[*].posts[*]
// Structure: {"users": [{"posts": [1,2]}, {"posts": [3,4]}]}

// CURRENT: Uses PathFilter (O(n) memory)
// 1. Parse entire JSON into memory
// 2. Apply JSONPath filter
// 3. Return all matches

// DESIRED: True streaming (O(element) memory)
// 1. Stream users array
// 2. For each user, stream posts array
// 3. Yield each post immediately
// 4. Discard user after processing
```

### Implementation Approach

#### Option A: Recursive Generator Streaming

Modify `Parser::streamFromArray()` to handle nested wildcards:

```php
private function streamFromArray(): \Generator
{
    // ...existing code...

    // When we hit a nested array that matches wildcard pattern
    if ($this->pathEvaluator->hasNestedWildcard()) {
        foreach ($this->parseArray() as $outerElement) {
            // Check if outer element has array at the path
            $innerPath = $this->pathEvaluator->getRemainingWildcardPath();
            $innerArray = $this->walkToArray($outerElement, $innerPath);

            if ($innerArray !== null) {
                foreach ($innerArray as $innerElement) {
                    yield $innerElement;
                }
            }
        }
        return;
    }
    // ...rest of method...
}
```

#### Option B: Multi-Level Path Evaluator

Add a multi-level streaming mode to `PathEvaluator`:

```php
// Track which wildcard level we're at
private int $currentWildcardLevel = 0;
private int $totalWildcardLevels = 0;

public function enterNextWildcardLevel(): void
{
    $this->currentWildcardLevel++;
}

public function isAtFinalWildcard(): bool
{
    return $this->currentWildcardLevel === $this->totalWildcardLevels;
}
```

#### Option C: Hybrid Approach (Recommended)

Stream the outer array, but buffer only the current outer element:

```php
// Memory: O(single outer element)
foreach ($this->parseArray() as $outerIndex => $outerElement) {
    $this->pathEvaluator->enterLevel($outerIndex, $outerElement);

    // Inner wildcard path segments
    $innerMatches = $this->walkAndCollect($outerElement, $remainingPath);

    foreach ($innerMatches as $match) {
        yield $match;
    }

    $this->pathEvaluator->exitLevel();
    // $outerElement is now eligible for GC
}
```

### Files to Modify

1. **`src/Internal/JsonPath/PathExpression.php`**
   - Modify `canUseSimpleStreaming()` to allow multiple wildcards
   - Add `getWildcardCount()` method
   - Add `getWildcardPositions()` to identify where wildcards are

2. **`src/Internal/Parser.php`**
   - Enhance `streamFromArray()` to handle nested wildcards
   - Add `walkAndStream()` method for nested extraction
   - Handle arbitrary nesting depth

3. **`src/Internal/JsonPath/PathEvaluator.php`**
   - Add multi-level wildcard tracking
   - Implement `getRemainingPathAfterWildcard()`
   - Track wildcard consumption during evaluation

4. **Tests**
   - Add `tests/Integration/NestedWildcardStreamingTest.php`
   - Add memory benchmark for nested patterns

### Example Patterns to Support

| Pattern | Structure | Expected Matches |
|---------|-----------|-----------------|
| `$.a[*].b[*]` | `{"a":[{"b":[1,2]},{"b":[3]}]}` | `[1,2,3]` |
| `$.users[*].posts[*]` | `{"users":[{"posts":[...]}]}` | All posts |
| `$.matrix[*][*]` | `{"matrix":[[1,2],[3,4]]}` | `[1,2,3,4]` |
| `$.data[*].items[*].tags[*]` | 3-level nesting | All tags |

### Edge Cases

1. **Empty nested arrays**: `{"users": [{"posts": []}]}`
   - Should yield nothing, not error

2. **Missing nested property**: `{"users": [{"name": "Alice"}]}`
   - When `posts` doesn't exist, skip silently

3. **Mixed types in array**: `{"data": [{"items": [1]}, "string", null]}`
   - Only process objects with matching structure

4. **Very deep nesting**: `$.a[*].b[*].c[*].d[*]`
   - Should work for arbitrary depth (4+ levels)

5. **Nested wildcards with property access**: `$.users[*].posts[*].author.name`
   - Stream users and posts, walk author.name

## Dependencies
- Task 24: Streaming JSONPath Memory Optimization (completed)
- Task 25: Complex Pattern Streaming (completed)

## Estimated Complexity
**High** - Requires recursive streaming logic with proper path tracking across multiple wildcard levels

## Implementation Notes

### Performance Considerations
- **Memory**: Target O(single outer element) per yield
- **CPU**: Additional overhead for nested iteration, but acceptable
- **GC**: Ensure outer elements are garbage collected after processing

### Backward Compatibility
- Existing PathFilter behavior must be preserved for recursive descent (`$..prop`)
- Tests must pass with both streaming and PathFilter producing identical results

### Testing Strategy
1. Create nested structure JSON files for testing
2. Compare streaming results with PathFilter for correctness
3. Measure memory usage during streaming
4. Benchmark performance vs PathFilter for large nested structures

### When to Fall Back to PathFilter
Keep PathFilter fallback for:
- Recursive descent patterns (`$..prop`)
- Patterns mixing wildcards with filters across levels
- Very complex patterns that would require excessive complexity

## Acceptance Criteria
- [ ] `$.users[*].posts[*]` uses streaming (not PathFilter fallback)
- [ ] `$.matrix[*][*]` streams correctly for 2D arrays
- [ ] `$.a[*].b[*].c[*]` works for 3+ level nesting
- [ ] Memory usage is O(single outer element) during streaming
- [ ] All existing tests pass (568+ tests)
- [ ] New integration tests cover all edge cases
- [ ] Performance benchmark shows improvement over PathFilter for large nested structures
- [ ] Code follows project conventions (PSR-12, PHPStan clean, Pint formatted)

## Success Metrics
- Memory: <10MB delta for streaming 10,000 nested items
- Correctness: 100% match with PathFilter results
- Performance: At least 50% memory reduction vs PathFilter for nested structures
