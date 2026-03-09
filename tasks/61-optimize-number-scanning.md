---
title: Optimize Number Scanning
status: todo
priority: High
description: Replace per-digit ord()/ctype_digit() calls with bulk numeric scanning
---

## Objectives
- Reduce per-digit overhead in `scanNumber()` by scanning digit sequences in bulk
- Replace individual `ord()` and `ctype_digit()` calls with buffer-level operations
- Improve number parsing throughput

## Deliverables
1. Refactored `scanNumber()` with bulk digit scanning
2. Benchmarks showing number parsing improvement
3. All existing tests passing (including overflow detection from Task 40)

## Technical Details

**Location:** `src/Internal/Lexer.php:354-492`

**Current Issue:**
Each digit in a number requires:
1. `peek()` call (method call + bounds check)
2. `ctype_digit()` call (function call)
3. `readByte()` call (method call + bounds check + position tracking)
4. `ord($char) - ord('0')` conversion

For a number like `1234567890`, that's 10 iterations with 4+ function calls each = 40+ function calls per number.

**Profiling Data:**
- Number throughput: 4.74 MB/s (slowest of all token types)
- Numbers in JSON benchmarks are typically 1-10 digits

**Proposed Solution:**
```php
private function scanNumber(string $firstChar, int $line, int $column): Token
{
    $buf = $this->buffer->getBufferRef();
    $pos = $this->buffer->getBufferPosition();
    $len = $this->buffer->getBufferLength();

    // Collect all number characters in one pass
    $numStr = $firstChar;
    $isFloat = false;

    while ($pos < $len) {
        $ch = $buf[$pos];
        if (($ch >= '0' && $ch <= '9') || $ch === '.' || $ch === 'e' || $ch === 'E' || $ch === '+' || $ch === '-') {
            if ($ch === '.' || $ch === 'e' || $ch === 'E') {
                $isFloat = true;
            }
            $numStr .= $ch;
            $pos++;
        } else {
            break;
        }
    }
    $this->buffer->setBufferPosition($pos);

    // Validate and convert the collected number string
    // Use a single conversion instead of per-digit arithmetic
    if ($isFloat) {
        $value = (float) $numStr;
    } else {
        // Check for overflow
        $value = filter_var($numStr, FILTER_VALIDATE_INT);
        if ($value === false) {
            $value = (float) $numStr; // Overflow to float
        }
    }

    return new Token(TokenType::NUMBER, $value, $line, $column);
}
```

**Alternative: Hybrid approach**
Keep the current digit-by-digit integer accumulation (for precision) but use bulk scanning to first extract the number string, then parse it:

```php
// Step 1: Extract number substring from buffer (fast, one pass)
$start = $pos - 1; // include firstChar
while ($pos < $len && isNumberChar($buf[$pos])) { $pos++; }
$numStr = substr($buf, $start, $pos - $start);

// Step 2: Validate RFC 8259 format (no leading zeros, etc.)
// Step 3: Convert to int or float
```

## Dependencies
- Task 57 (buffer optimization) - requires direct buffer access

## Estimated Complexity
**Medium** - Number validation logic exists; refactoring to bulk scan

## Implementation Notes
- Must preserve RFC 8259 validation: no leading zeros, required digit after decimal, etc.
- Must preserve overflow detection from Task 40
- `filter_var()` with `FILTER_VALIDATE_INT` handles overflow detection natively
- Consider using `json_decode()` on the number string as a fast path (it handles all edge cases)
- Numbers rarely span buffer boundaries, but handle that case
- The `-` sign prefix is already consumed before `scanNumber()` is called

**Test Cases:**
- Integers: 0, 1, -1, PHP_INT_MAX, PHP_INT_MAX+1 (overflow to float)
- Floats: 1.0, -1.5, 0.001, 1e10, 1.5E-3
- Edge cases: leading zeros (should fail), trailing dot (should fail), bare minus (should fail)
- Very large numbers
- Numbers at buffer boundaries

## Acceptance Criteria
- [ ] Number scanning collects digits in bulk from buffer
- [ ] RFC 8259 number format validation preserved
- [ ] Integer overflow detection still works (Task 40)
- [ ] All existing tests pass (598+ tests)
- [ ] PHPStan analysis passes
- [ ] Number parsing benchmark shows >= 30% throughput improvement
