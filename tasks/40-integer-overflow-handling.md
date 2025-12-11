---
title: Add Integer Overflow Handling in Number Parsing
status: todo
priority: Medium
description: Detect and handle integer overflow on 32-bit systems during number parsing
---

## Objectives
- Detect integer overflow during number parsing before it occurs
- Automatically convert to float when approaching PHP_INT_MAX
- Ensure consistent behavior across 32-bit and 64-bit PHP installations
- Maintain accuracy for large integers within platform limits

## Deliverables
1. Enhanced number parsing in `Lexer` with overflow detection
2. Automatic conversion to float when integer would overflow
3. Unit tests covering edge cases for both 32-bit and 64-bit limits
4. Documentation of number handling behavior

## Technical Details

**Location:** `src/Internal/Lexer.php:320-444`

**Current Issue:**
```php
while (true) {
    // ...
    $intPart = $intPart * 10 + (ord($char) - ord('0'));
    // No overflow check - can wrap on 32-bit systems
}
```

**Problem:**
- 32-bit systems: `PHP_INT_MAX` = 2,147,483,647
- 64-bit systems: `PHP_INT_MAX` = 9,223,372,036,854,775,807
- Numbers exceeding `PHP_INT_MAX` silently overflow to negative values
- Example: On 32-bit, parsing `2147483648` might produce `-2147483648`

**Proposed Solution:**
```php
private function parseNumber(): Token
{
    $isNegative = false;
    $char = $this->buffer->current();

    if ($char === '-') {
        $isNegative = true;
        $this->buffer->advance();
        $char = $this->buffer->current();
    }

    $intPart = 0;
    $hasDecimal = false;
    $hasExponent = false;
    $useFloat = false;

    // Calculate threshold for overflow detection
    $maxIntBeforeOverflow = (int)(PHP_INT_MAX / 10);
    $maxLastDigit = PHP_INT_MAX % 10;

    // Parse integer part
    while ($char !== null && ctype_digit($char)) {
        $digit = ord($char) - ord('0');

        // Check for overflow before multiplication
        if ($intPart > $maxIntBeforeOverflow ||
            ($intPart === $maxIntBeforeOverflow && $digit > $maxLastDigit)) {
            // Would overflow - switch to float
            $useFloat = true;
            break;
        }

        $intPart = $intPart * 10 + $digit;
        $this->buffer->advance();
        $char = $this->buffer->current();
    }

    // If overflow detected, parse as float from the start
    if ($useFloat) {
        return $this->parseAsFloat($isNegative, $intPart);
    }

    // Continue with decimal/exponent parsing...
    // If decimal or exponent found, convert to float
    // Otherwise return as integer
}

private function parseAsFloat(bool $isNegative, int $partialInt): Token
{
    // Rewind to start of number and parse entire number as string
    // Convert string to float using (float) cast
    // This preserves precision better than continuing with int math
}
```

**Alternative Approach (Using bcmath or gmp):**
```php
// For arbitrary precision (if bcmath available)
if (extension_loaded('bcmath')) {
    $value = bcadd($value, (string)$digit);
    if (bccomp($value, (string)PHP_INT_MAX) > 0) {
        $useFloat = true;
    }
}
```

## Dependencies
- None

## Estimated Complexity
**Medium** - Requires careful overflow detection logic and thorough testing

## Implementation Notes
- Must handle both positive and negative overflow
- Negative numbers have different limit: `PHP_INT_MIN` = `-PHP_INT_MAX - 1`
- Consider performance impact of overflow checks
- Float conversion should happen as early as possible to minimize precision loss
- Test on both 32-bit and 64-bit systems (use Docker containers)
- RFC 8259 allows arbitrary precision, but most implementations use float for large numbers

**Test Cases:**
- 32-bit edge cases: `2147483647`, `2147483648`, `-2147483648`, `-2147483649`
- 64-bit edge cases: `9223372036854775807`, `9223372036854775808`
- Very large numbers: `999999999999999999999999999`
- Numbers with many leading zeros: `00000000002147483648`

## Acceptance Criteria
- [ ] Integer overflow is detected before it occurs
- [ ] Numbers exceeding PHP_INT_MAX are converted to float
- [ ] Behavior is consistent across 32-bit and 64-bit PHP
- [ ] Tests verify correct handling at platform-specific limits
- [ ] Tests include very large numbers beyond both 32-bit and 64-bit limits
- [ ] Performance impact is minimal (overflow checks are fast)
- [ ] Documentation explains number handling behavior
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
