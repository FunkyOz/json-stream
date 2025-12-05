---
title: Complete PathEvaluator Coverage
status: todo
priority: High
description: Add tests for PathEvaluator structural matching and value-based logic
---

## Objectives
- Achieve 100% coverage for PathEvaluator class
- Test structural matching methods
- Cover value-based matching logic

## Deliverables
1. Tests for matchesStructure() method (lines 82-91)
2. Tests for needsValueForMatch() (line 109)
3. Tests for matchSegmentsStructural() (lines 137-213)
4. Tests for navigateToNextMatch() (lines 300, 314-318)

## Technical Details

### Current Coverage Gap
- **PathEvaluator**: 55.8% coverage
- **Missing lines**: 82-91, 109, 137-213, 300, 314-318

### Key Uncovered Methods

1. **matchesStructure()** - Check if path structure matches without evaluating filters
2. **needsValueForMatch()** - Determine if current segment requires value parsing
3. **matchSegmentsStructural()** - Recursive structural matching
4. **navigateToNextMatch()** - Navigate path to next matching position

These are advanced streaming optimization methods used when filtering with JSONPath.

### Test Scenarios Needed

1. Test matchesStructure() with various path depths
2. Test needsValueForMatch() with filter segments
3. Test needsValueForMatch() with non-filter segments
4. Test matchSegmentsStructural() recursive logic
5. Test navigateToNextMatch() with wildcards
6. Test navigateToNextMatch() with filters

## Dependencies
- Task 30 (Parser coverage) - Parser integration needed

## Estimated Complexity
**Medium** - Complex streaming logic, requires understanding of PathEvaluator's role in optimization.

## Implementation Notes

```php
test('PathEvaluator matchesStructure returns true for root-only path at depth 0', function () {
    $expression = PathParser::parse('$');
    $evaluator = new PathEvaluator($expression);

    expect($evaluator->matchesStructure())->toBeTrue();
});

test('PathEvaluator needsValueForMatch returns true for filter segments', function () {
    $expression = PathParser::parse('$.items[?(@.price > 10)]');
    $evaluator = new PathEvaluator($expression);

    // Navigate to filter segment
    $evaluator->enterProperty('items');
    $evaluator->enterArrayElement(0);

    expect($evaluator->needsValueForMatch())->toBeTrue();
});
```

## Acceptance Criteria
- [ ] All listed line ranges covered
- [ ] matchesStructure() fully tested
- [ ] needsValueForMatch() fully tested
- [ ] Structural matching logic tested
- [ ] Navigation logic tested
- [ ] Coverage shows 100% for PathEvaluator
- [ ] All tests pass

## Success Metrics
- PathEvaluator: 55.8% -> 100%
