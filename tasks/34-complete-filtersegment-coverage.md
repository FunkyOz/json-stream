---
title: Complete FilterSegment Coverage
status: todo
priority: High
description: Add tests for FilterSegment comparison operators
---

## Objectives
- Achieve 100% coverage for FilterSegment class
- Test all comparison operators and edge cases

## Deliverables
1. Tests for lines 71, 140, 148, 162, 164 (comparison operators and edge cases)

## Technical Details

### Current Coverage Gap
- **FilterSegment**: 90.6% coverage
- **Missing lines**: 71, 140, 148, 162, 164

### Test Scenarios
1. Test all comparison operators (<, >, <=, >=, ==, !=)
2. Test filters with null values
3. Test filters with boolean comparisons
4. Test filters on non-array values
5. Test filters with missing properties

### Example Test
```php
test('FilterSegment handles comparison with null value', function () {
    $expression = PathParser::parse('$[?(@.value == null)]');
    $evaluator = new PathEvaluator($expression);

    $evaluator->enterArrayElement(0);
    $match = $evaluator->matchesValue(['value' => null]);

    expect($match)->toBeTrue();
});
```

## Dependencies
- Task 31 (PathEvaluator)

## Estimated Complexity
**Low** - Straightforward comparison operator tests.

## Acceptance Criteria
- [ ] All listed lines covered
- [ ] All comparison operators tested
- [ ] Edge cases tested
- [ ] Coverage shows 100%

## Success Metrics
- FilterSegment: 90.6% -> 100%
