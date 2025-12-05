---
title: Complete RootSegment Coverage
status: todo
priority: High
description: Add tests for RootSegment matching logic
---

## Objectives
- Achieve 100% coverage for RootSegment class
- Test root segment matching at various depths

## Deliverables
1. Tests for line 16 (root segment matching logic)

## Technical Details

### Current Coverage Gap
- **RootSegment**: 50.0% coverage
- **Missing line**: 16

### Test Scenarios
1. Test root segment matches at depth 0
2. Test root segment doesn't match at depth > 0
3. Test root segment with value

### Example Test
```php
test('RootSegment matches only at depth 0', function () {
    $segment = new RootSegment();

    // At depth 0 (root level)
    expect($segment->matches(0, null, null, null))->toBeTrue();

    // At depth > 0 (nested)
    expect($segment->matches(1, 'prop', null, null))->toBeFalse();
});
```

## Dependencies
- Task 31 (PathEvaluator)

## Estimated Complexity
**Low** - Very simple class with minimal logic.

## Acceptance Criteria
- [ ] Line 16 covered
- [ ] Root matching logic tested
- [ ] Coverage shows 100%

## Success Metrics
- RootSegment: 50.0% -> 100%
