---
title: Document and Handle Negative Array Index Limitations in Streaming Mode
status: todo
priority: High
description: Document limitations of negative indices in streaming mode and add validation
---

## Objectives
- Document that negative array indices cannot work in streaming mode
- Add validation to detect negative indices in JSONPath expressions
- Provide clear error messages or warnings when negative indices are used
- Consider buffering strategy for arrays when negative indices are detected

## Deliverables
1. Updated `ArraySliceSegment` to detect and handle negative indices appropriately
2. Documentation in API docs and README about streaming mode limitations
3. Exception or warning when negative indices are used with streaming patterns
4. Unit tests verifying error handling for negative indices
5. Optional: Buffering implementation for negative index support (if feasible)

## Technical Details

**Location:** `src/Internal/JsonPath/ArraySliceSegment.php:26-51`

**Current Issue:**
```php
public function matches(string|int $key, mixed $value, int $depth): bool
{
    $start = $this->start ?? 0;
    // Negative indices cannot work correctly in streaming mode
    // because we don't know the array length
}
```

**Examples of Problematic Patterns:**
- `$[-1]` - Last element (need to know array length)
- `$[-3:]` - Last 3 elements (need to know array length)
- `$[-5:-2]` - Slice from end (need to know array length)

**Proposed Solution (Option 1: Throw Exception):**
```php
public function __construct(?int $start, ?int $end, ?int $step)
{
    if (($start !== null && $start < 0) || ($end !== null && $end < 0)) {
        throw new PathException(
            'Negative array indices are not supported in streaming mode. ' .
            'Use readAllMatches() to buffer the entire array.'
        );
    }

    $this->start = $start;
    $this->end = $end;
    $this->step = $step ?? 1;
}
```

**Proposed Solution (Option 2: Auto-Buffer):**
```php
// In PathEvaluator or similar
public function evaluatePath(string $path, StreamReader $reader): iterable
{
    $expression = $this->parser->parse($path);

    // Check if expression uses negative indices
    if ($expression->hasNegativeIndices()) {
        // Fall back to buffering mode
        return $this->evaluateWithBuffering($expression, $reader);
    }

    // Use streaming mode
    return $this->evaluateStreaming($expression, $reader);
}
```

## Dependencies
- None

## Estimated Complexity
**Medium** - Option 1 is straightforward; Option 2 requires buffering implementation

## Implementation Notes
- Streaming mode processes array elements one at a time without knowing total length
- Negative indices require knowing array length: `array[-1]` = `array[length - 1]`
- Three approaches:
  1. **Strict Mode:** Throw exception during path parsing (recommended for v1)
  2. **Auto-Buffer Mode:** Automatically switch to buffered parsing when negative indices detected
  3. **Documentation Only:** Document limitation and let users choose approach
- Consider impact on JSONPath compatibility (some implementations support negative indices)
- May need to add `hasNegativeIndices()` method to `PathExpression`

**Affected Classes:**
- `ArraySliceSegment` - slice operations with negative indices
- `ArrayIndexSegment` - single negative index access
- `PathParser` - detection during parsing
- `PathExpression` - analysis of segments

## Acceptance Criteria
- [ ] Negative indices in JSONPath expressions are detected
- [ ] Clear error message or warning is provided for negative indices
- [ ] Documentation explains streaming mode limitations
- [ ] Documentation provides workaround (use `readAllMatches()`)
- [ ] Tests verify error handling for various negative index patterns
- [ ] Consider adding `PathExpression::hasNegativeIndices()` method
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
