---
title: Verify PathEvaluator Coverage
status: todo
priority: High
description: Verify that PathEvaluator line 151 is actually covered by existing tests
---

## Objectives
- Confirm that PathEvaluator.php line 151 is covered by existing tests
- Update coverage data if tool reporting is inaccurate
- Add additional test if genuinely uncovered

## Deliverables
1. Verification test for line 151 (`canTerminateEarly()` returning false)
2. Coverage report showing line 151 as covered

## Technical Details

### Current Coverage Gap
- **PathEvaluator.php**: 98.8% coverage
- **Missing line**: 151

### Code in Question
```php
public function canTerminateEarly(): bool
{
    $terminationIndex = $this->expression->getTerminationIndex();
    if ($terminationIndex === null) {
        return false;  // Line 151 - Reported as NOT COVERED
    }

    if ($this->currentDepth === 0) {
        return false;
    }
    // ... rest of method
}
```

### Analysis
This line should be covered by existing test in `PathEvaluatorTest.php`:
```php
it('canTerminateEarly returns false for expressions without early termination', function (): void {
    $parser = new PathParser();
    $expression = $parser->parse('$.items[*]');  // Wildcard has null termination
    $evaluator = new PathEvaluator($expression);

    $evaluator->enterLevel('items', []);
    $evaluator->enterLevel(0, ['id' => 1]);

    expect($evaluator->canTerminateEarly())->toBeFalse();  // Should cover line 151
});
```

### Possible Causes
1. Coverage tool caching issue
2. Test is not actually executing this code path
3. PHPUnit coverage driver issue

## Implementation Steps

1. **Verify Current Test**
   ```bash
   docker compose run --rm php vendor/bin/pest tests/Unit/JsonPath/PathEvaluatorTest.php --coverage --min=0
   ```

2. **Add Explicit Test** (if needed)
   ```php
   it('canTerminateEarly returns false when termination index is null', function (): void {
       $parser = new PathParser();

       // Test all path types that should return null termination index
       $paths = [
           '$.items[*]',           // Wildcard
           '$.items[-1]',          // Negative index
           '$.items[0:]',          // Unbounded slice
           '$.items[0:0]',         // Zero-end slice
       ];

       foreach ($paths as $path) {
           $expression = $parser->parse($path);
           $evaluator = new PathEvaluator($expression);

           $evaluator->enterLevel('items', []);
           $evaluator->enterLevel(0, ['test' => 'value']);

           expect($evaluator->canTerminateEarly())
               ->toBeFalse("Path {$path} should not terminate early");
       }
   });
   ```

3. **Verify Coverage Improved**
   ```bash
   docker compose run --rm php vendor/bin/pest --coverage --min=0 | grep PathEvaluator
   ```

## Dependencies
- None (verification task)

## Estimated Complexity
**Low** - 30-60 minutes. Simple verification or test addition.

## Implementation Notes

If the line is genuinely uncovered, it indicates a gap in the test for `getTerminationIndex()` returning `null`. The test should be straightforward to add.

If the line is already covered, this is a known issue with PHPUnit coverage driver and can be documented.

## Acceptance Criteria
- [x] Verified whether line 151 is actually executed by existing tests
- [x] Added new test if gap found
- [x] Coverage report shows PathEvaluator at 100% or documents tool limitation
- [x] All tests pass

## Success Metrics
After completion:
- PathEvaluator: 98.8% → 100% (if genuine gap)
- Or: Document as coverage tool limitation (if already covered)
- **Expected Coverage Gain:** +0.1%
