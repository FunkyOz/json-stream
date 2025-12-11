---
title: Optimize isAssociativeArray() Method
status: todo
priority: Low
description: Simplify isAssociativeArray() method to remove redundant empty() check
---

## Objectives
- Remove redundant `empty()` check from `isAssociativeArray()` method
- Improve code clarity and micro-optimize performance
- Maintain correct behavior for edge cases

## Deliverables
1. Simplified `isAssociativeArray()` implementation in `PathFilter`
2. Unit tests verifying behavior with empty arrays, sequential arrays, and associative arrays
3. Performance benchmark comparing old vs new implementation (optional)

## Technical Details

**Location:** `src/Internal/JsonPath/PathFilter.php:86-93`

**Current Implementation:**
```php
private function isAssociativeArray(array $array): bool
{
    if (empty($array)) {
        return false;
    }
    return !array_is_list($array);
}
```

**Issue:**
- `array_is_list([])` returns `true` (empty array is considered a list)
- Therefore, `!array_is_list([])` returns `false`
- The `empty()` check is redundant since the result is already `false` for empty arrays

**Proposed Solution:**
```php
private function isAssociativeArray(array $array): bool
{
    return !empty($array) && !array_is_list($array);
}
```

**Verification:**
```php
// Test cases:
array_is_list([])                    // true  -> !true = false -> !empty && false = false ✓
array_is_list([1, 2, 3])            // true  -> !true = false -> !empty && false = false ✓
array_is_list(['a' => 1, 'b' => 2]) // false -> !false = true -> !empty && true = true ✓
```

## Dependencies
- None

## Estimated Complexity
**Low** - Simple one-line optimization

## Implementation Notes
- This is a micro-optimization with minimal performance impact
- Main benefit is code clarity - intention is more explicit
- `array_is_list()` was introduced in PHP 8.1
- The function checks if array keys are sequential integers starting from 0
- Empty arrays are considered lists by `array_is_list()`

**Edge Cases to Test:**
```php
[]                           // empty array -> false (not associative)
[1, 2, 3]                   // sequential list -> false (not associative)
[0 => 'a', 1 => 'b']        // explicit sequential keys -> false (not associative)
[1 => 'a', 2 => 'b']        // non-zero sequential keys -> true (associative)
['a' => 1, 'b' => 2]        // string keys -> true (associative)
[0 => 'a', 2 => 'b']        // gap in keys -> true (associative)
[1 => 'a', 0 => 'b']        // wrong order -> true (associative)
```

## Acceptance Criteria
- [ ] Method simplified to single line: `return !empty($array) && !array_is_list($array);`
- [ ] Tests verify correct behavior for empty arrays
- [ ] Tests verify correct behavior for sequential arrays
- [ ] Tests verify correct behavior for associative arrays
- [ ] Tests verify correct behavior for edge cases (gaps, wrong order, etc.)
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
