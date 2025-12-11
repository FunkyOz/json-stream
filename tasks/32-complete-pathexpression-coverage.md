---
title: Complete PathExpression Coverage
status: done
priority: High
description: Add tests for PathExpression utility methods
---

## Objectives
- Achieve 100% coverage for PathExpression class
- Test all getter methods and path reconstruction

## Deliverables
1. Tests for getOriginalPath() method (line 34)
2. Tests for getSegmentCount() method (lines 95-173, 198)
3. Tests for path segment management

## Technical Details

### Current Coverage Gap
- **PathExpression**: 53.8% coverage
- **Missing lines**: 34, 95-173, 198

### Test Scenarios
1. Test getOriginalPath() returns correct path string
2. Test getSegments() returns segment array
3. Test getSegmentCount() returns correct count
4. Test with various path complexities

### Example Test
```php
test('PathExpression getOriginalPath returns original path string', function () {
    $path = '$.items[*].name';
    $expression = PathParser::parse($path);

    expect($expression->getOriginalPath())->toBe($path);
});

test('PathExpression getSegmentCount returns correct count', function () {
    $expression = PathParser::parse('$.a.b.c');

    expect($expression->getSegmentCount())->toBe(4); // root + a + b + c
});
```

## Dependencies
- Task 31 (PathEvaluator)

## Estimated Complexity
**Low** - Simple getter method tests.

## Acceptance Criteria
- [x] All line ranges covered
- [x] All getter methods tested
- [x] Coverage shows 100%
- [x] All tests pass

## Success Metrics
- PathExpression: 88.5% -> 100% ✅
