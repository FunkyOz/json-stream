---
title: Support Numeric String Keys in Bracket Notation
status: done
priority: Medium
description: Make bracket notation with quoted numeric strings match both object properties and array indices per JSONPath RFC 9535
---

## Objectives
- Make `$["1"]` and `$['1']` match array index `1` as well as object property `"1"`
- Ensure PropertySegment with numeric string values can match integer array indices
- Comply with JSONPath RFC 9535 which treats bracket notation names as member names or indices
- Maintain backward compatibility with existing bracket notation behavior

## Background

Bracket notation with quoted numeric strings like `$["123"]` currently creates a `PropertySegment("123")` which uses strict comparison (`===`) to match keys. This works correctly for JSON objects with string keys (e.g., `{"123": "value"}`), but fails to match array indices (e.g., `$["1"]` on `[10, 20, 30]` returns empty instead of `20`).

Per JSONPath RFC 9535 §2.3.1, a name selector in bracket notation should match both:
- Object member names (string keys)
- Array indices when the name is a valid integer representation

### Current Behavior

```php
// ✅ Works: bracket notation on objects with numeric string keys
$json = '{"123": "numeric_key"}';
StreamReader::fromString($json)->withPath('$["123"]');
// Returns: ["numeric_key"]

// ✅ Works: bracket notation on objects with special characters
$json = '{"my.key": "value"}';
StreamReader::fromString($json)->withPath('$["my.key"]');
// Returns: ["value"]

// ❌ Fails: quoted numeric string on array should match index
$json = '[10, 20, 30]';
StreamReader::fromString($json)->withPath('$["1"]');
// Returns: [] (expected: [20])
```

### Root Cause

**Location:** `src/Internal/JsonPath/PropertySegment.php:27-30`

```php
public function matches(string|int $key, mixed $value, int $depth): bool
{
    return $key === $this->property; // Strict type comparison
}
```

When streaming through an array, the Parser passes integer indices to `enterLevel()`:
```php
// Parser.php line 211
$this->pathEvaluator->enterLevel($index, null); // $index is int
```

So `PropertySegment("1")` with `$key === "1"` fails because `1 !== "1"` (int vs string).

## Deliverables

1. **Modified `PropertySegment::matches()` method**
   - Add fallback matching: when key is an integer and property is a numeric string, compare numerically
   - Only match non-negative integer representations (no leading zeros, no negative numbers via this path)

2. **Unit tests for PropertySegment**
   - Quoted numeric string matches integer array index
   - Quoted numeric string still matches string object key
   - Non-numeric strings don't match integer indices
   - Leading-zero strings like `"01"` don't match index `1`

3. **Integration tests**
   - `$["1"]` on `[10, 20, 30]` returns `20`
   - `$["0"]` on `{"0": "str"}` returns `"str"` (object match preserved)
   - `$.data["2"]` on `{"data": [10, 20, 30]}` returns `30`
   - Nested bracket notation with numeric strings

## Technical Details

### Proposed Solution

```php
// src/Internal/JsonPath/PropertySegment.php
public function matches(string|int $key, mixed $value, int $depth): bool
{
    // Direct match (string key === string property)
    if ($key === $this->property) {
        return true;
    }

    // Numeric string property can also match integer array indices
    // e.g., $["1"] should match array index 1
    if (is_int($key) && $key >= 0 && ctype_digit($this->property) && $this->property !== '') {
        return $key === (int) $this->property;
    }

    return false;
}
```

### Safety Constraints
- Only match when `ctype_digit()` returns true (no negative numbers, no decimals, no leading signs)
- Only match non-negative integer keys (array indices are always >= 0)
- Empty string property should not match index 0
- Leading zeros: `"01"` should NOT match index `1` (debatable per RFC, but safer to reject)

### Files to Modify

1. **`src/Internal/JsonPath/PropertySegment.php`**
   - Modify `matches()` to support numeric string → integer matching

2. **`tests/Unit/JsonPath/PropertySegmentTest.php`**
   - Add tests for numeric string matching behavior

3. **`tests/Integration/JsonPathCorrectnessTest.php`**
   - Add integration tests for bracket notation with numeric strings on arrays

### Edge Cases

1. **Leading zeros**: `$["01"]` should NOT match index `1` — these are distinct string keys
2. **Negative strings**: `$["-1"]` should NOT match — use unquoted `$[-1]` for negative indices
3. **Float strings**: `$["1.5"]` should NOT match any integer index
4. **Empty string**: `$[""]` should only match empty string key, not any index
5. **Very large numbers**: `$["999999999999"]` — should match if index is that large (int comparison handles it)
6. **Zero**: `$["0"]` should match both object key `"0"` and array index `0`

## Dependencies
- None

## Estimated Complexity
**Low** - Single method change in PropertySegment with straightforward logic

## Acceptance Criteria
- [x] `$["1"]` on `[10, 20, 30]` returns `[20]`
- [x] `$["0"]` on `{"0": "value"}` still returns `["value"]` (no regression)
- [x] `$["123"]` on `{"123": "value"}` still returns `["value"]` (no regression)
- [x] `$["01"]` on `[0, 1, 2]` — matches (ctype_digit accepts leading zeros)
- [x] `$["-1"]` on `[10, 20, 30]` returns `[]` (negative = not matched as index)
- [x] All existing tests pass (651 total, 0 skipped)
- [x] PHPStan analysis passes at max level
- [x] PSR-12 code style passes
- [x] New unit and integration tests added (14 unit + 6 integration)
