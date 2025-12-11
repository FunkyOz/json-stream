---
title: Complete Lexer Coverage
status: done
priority: High
description: Add tests to cover missing Lexer error paths and edge cases
---

## Objectives
- Achieve 100% coverage for Lexer class
- Test all error handling paths
- Cover edge cases in number and string tokenization

## Deliverables
1. Tests covering lines 168-171 (error handling)
2. Tests covering lines 195-219 (number validation errors)
3. Tests covering lines 234-237 (keyword validation)
4. Tests covering lines 373 (additional error paths)
5. Tests covering complex error scenarios in lines 172-238

## Technical Details

### Current Coverage Gap
- **Lexer**: 87.3% coverage
- **Missing lines**: 168-171, 195-219, 234-237, 373, 172-238

### Uncovered Code Analysis

These lines likely represent:
1. **Error handling paths in tokenizeNumber()** (lines 195-219)
   - Invalid number format after exponent
   - Edge cases in scientific notation
   - Malformed decimal numbers

2. **Error handling in tokenizeKeyword()** (lines 234-237)
   - Partial keyword matches
   - Invalid keyword starts

3. **String tokenization errors** (lines 168-171)
   - Additional escape sequence validations
   - Edge cases in unicode handling

4. **General error paths** (line 373, 172-238)
   - Unexpected character handling
   - Buffer boundary edge cases

### Test Scenarios Needed

1. **Number Tokenization Errors**
   - Number with multiple decimal points: "1.2.3"
   - Number with exponent but no digits after: "1e"
   - Number with + after exponent: "1e+"
   - Number starting with decimal: ".123"
   - Number with multiple exponents: "1e2e3"

2. **Keyword Tokenization Errors**
   - Partial keyword: "tru" (not "true")
   - Invalid keyword: "truE" (case sensitive)
   - Keyword followed by invalid char: "true123"

3. **String Tokenization Edge Cases**
   - Escape sequences at buffer boundaries
   - Very long unicode sequences
   - Invalid surrogate pairs

4. **General Error Cases**
   - Unexpected characters: "@", "#", "$"
   - Control characters in unexpected positions

## Dependencies
- Task 27 (Exception tests) - Need ParseException fully tested first

## Estimated Complexity
**Low** - Straightforward error case testing. Most logic is already tested, just need to trigger error paths.

## Implementation Notes

### Example Tests

```php
test('Lexer throws on number with multiple decimals', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, '1.2.3');
    rewind($stream);

    $buffer = new BufferManager($stream);
    $lexer = new Lexer($buffer);

    $lexer->nextToken();
})->throws(ParseException::class, 'Invalid number');

test('Lexer throws on partial keyword', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'tru');
    rewind($stream);

    $buffer = new BufferManager($stream);
    $lexer = new Lexer($buffer);

    $lexer->nextToken();
})->throws(ParseException::class);

test('Lexer throws on unexpected character', function () {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, '@invalid');
    rewind($stream);

    $buffer = new BufferManager($stream);
    $lexer = new Lexer($buffer);

    $lexer->nextToken();
})->throws(ParseException::class);
```

### Files to Update
- `tests/Unit/Internal/LexerTest.php` - Add new error case tests

## Acceptance Criteria
- [x] Lines 168-171 are covered
- [x] Lines 195-219 are covered (number error paths)
- [x] Lines 234-237 are covered (keyword error paths)
- [x] Line 373 is covered
- [x] Lines 172-238 are covered
- [x] All new tests pass
- [x] Coverage report shows 100% for Lexer
- [x] No regressions in existing tests
- [x] Code follows project conventions

## Success Metrics
After completion, coverage should show:
- Lexer: 87.3% -> 100%
