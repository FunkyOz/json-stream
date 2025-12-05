---
title: Complex Pattern Streaming Support
status: todo
priority: Low
description: Extend true streaming support to complex JSONPath patterns like $.users[*].name, nested wildcards, and filter expressions
---

## Objectives
- Extend streaming support beyond simple patterns (currently only `$.Ads[*]`, `$.prop[*]`)
- Implement true streaming for property access after wildcards (`$.users[*].name`)
- Implement true streaming for nested wildcards (`$.users[*].posts[*]`)
- Implement true streaming for filter expressions (`$.users[?(@.age > 18)]`)
- Maintain O(1) memory for all streamable patterns
- Preserve PathFilter fallback for recursive descent patterns (`$..prop`) which inherently require tree walking

## Background

Task 24 implemented a hybrid approach for JSONPath streaming:
- **Simple patterns** (e.g., `$.Ads[*]`) → True streaming with 0 MB memory delta
- **Complex patterns** → PathFilter fallback (loads entire JSON into memory)

This task extends true streaming to complex patterns currently using the fallback.

## Deliverables

1. **Property-after-wildcard streaming** (`$.users[*].name`)
   - Parse each array element
   - Extract specified property during parsing
   - Yield property value immediately
   - Discard rest of element

2. **Nested wildcard streaming** (`$.users[*].posts[*]`)
   - Stream outer array elements
   - Stream inner array elements per outer element
   - Yield all nested matches progressively

3. **Filter expression streaming** (`$.users[?(@.age > 18)]`)
   - Parse each array element
   - Evaluate filter expression
   - Yield if matches, discard if not

4. **Updated pattern detection**
   - Expand `canUseSimpleStreaming()` to classify more patterns as streamable
   - Keep recursive descent (`$..prop`) as non-streamable (requires tree walk)

5. **Comprehensive tests**
   - Memory tests for each pattern type
   - Correctness tests ensuring same results as PathFilter

## Technical Details

### Current Pattern Classification (PathExpression::canUseSimpleStreaming)

**Returns `true` (streaming):**
- `$.Ads[*]` - Root property + wildcard
- `$.prop.nested[*]` - Property chain + wildcard
- `$.array[0:10]` - Slice operations

**Returns `false` (PathFilter fallback):**
- `$.users[*].name` - Property after wildcard
- `$.users[0].name` - Property after index
- `$.users[*].posts[*]` - Multiple wildcards
- `$.users[?(@.age > 18)]` - Filter expressions
- `$..prop` - Recursive descent

### Implementation Approach

#### 1. Property-After-Wildcard: `$.users[*].name`

Current streaming only yields the array element. We need to:
1. Parse the array element (object) during streaming
2. Walk into the parsed value to extract the property
3. Yield the extracted property value

```php
// In streamFromArray() when we have property after wildcard
foreach ($this->parseArray() as $index => $value) {
    // Walk into value to extract remaining path segments
    $extracted = $this->walkValue($value, $remainingSegments);
    if ($extracted !== null) {
        yield $extracted;
    }
}
```

**Challenge:** We need to track which segments have been consumed vs remaining.

#### 2. Nested Wildcards: `$.users[*].posts[*]`

Need to handle multiple levels of streaming:
1. Stream outer array (users)
2. For each user, stream inner array (posts)
3. Yield each post progressively

```php
// Conceptual approach
foreach ($this->streamToPath('$.users') as $user) {
    foreach ($this->walkAndStream($user, '[*].posts[*]') as $post) {
        yield $post;
    }
}
```

#### 3. Filter Expressions: `$.users[?(@.age > 18)]`

Already partially implemented in `streamFromArray()`:
- Uses `needsValueForMatch()` to detect filters
- Parses value to evaluate filter
- Need to ensure this path is classified as streamable

### Files to Modify

1. **`src/Internal/JsonPath/PathExpression.php`**
   - Update `canUseSimpleStreaming()` to return `true` for more patterns
   - Add `getStreamingStrategy()` method to return strategy type

2. **`src/Internal/Parser.php`**
   - Enhance `streamFromArray()` to handle remaining path segments
   - Add `walkValue()` method to extract properties from parsed values
   - Handle nested wildcard streaming

3. **`src/Internal/JsonPath/PathEvaluator.php`**
   - Add methods to track consumed vs remaining segments
   - Add `getRemainingSegments()` for post-parse walking

4. **`src/Reader/ItemIterator.php`**
   - Update strategy selection if needed

### Pattern Streaming Strategies

| Pattern | Strategy | Memory | Implementation |
|---------|----------|--------|----------------|
| `$.Ads[*]` | Direct stream | O(1) | ✅ Done (Task 24) |
| `$.users[*].name` | Stream + walk | O(element) | New |
| `$.users[*].posts[*]` | Nested stream | O(element) | New |
| `$.users[?(@.age > 18)]` | Stream + filter | O(element) | Enhance existing |
| `$..prop` | PathFilter | O(n) | Keep as-is |

### Edge Cases

1. **Deep property access**: `$.users[*].profile.settings.theme`
   - Must walk multiple levels into parsed element

2. **Mixed wildcards and properties**: `$.users[*].posts[*].author.name`
   - Stream users, stream posts per user, walk author.name

3. **Empty results**: Pattern matches structure but finds no values
   - Should yield nothing, not error

4. **Non-existent properties**: `$.users[*].nonexistent`
   - Should yield nothing for elements missing the property

## Dependencies
- Task 24: Streaming JSONPath Memory Optimization (completed)

## Estimated Complexity
**High** - Requires sophisticated path segment tracking and multi-level streaming logic

## Implementation Notes

### Order of Implementation
1. Start with property-after-wildcard (most common use case)
2. Add filter expression full support
3. Implement nested wildcards (most complex)
4. Update pattern detection throughout

### Testing Strategy
- Run existing 126 JSONPath tests after each change
- Add memory tests for each new streaming pattern
- Compare results against PathFilter for correctness
- Performance benchmarks showing memory savings

### Potential Simplification
If full streaming for all patterns proves too complex, consider:
- Streaming the outer array only
- Buffering only the current element for property extraction
- This gives O(element) memory instead of O(1) but much better than O(n)

## Acceptance Criteria
- [ ] `$.users[*].name` uses streaming (not PathFilter fallback)
- [ ] `$.users[*].posts[*]` uses streaming
- [ ] `$.users[?(@.age > 18)]` uses streaming
- [ ] Memory usage is O(element) for these patterns, not O(n)
- [ ] All 126+ JSONPath tests pass
- [ ] Memory tests validate streaming behavior
- [ ] `$..prop` still correctly falls back to PathFilter
- [ ] Code follows project conventions (PSR-12, type safety)
