---
title: Optimize String Value Construction
status: todo
priority: Medium
description: Replace incremental string concatenation with array accumulation for building parsed string values
---

## Objectives
- Replace `$result .= $char` pattern in `scanString()` with array accumulation + final join
- Reduce memory reallocation overhead from growing strings character by character

## Deliverables
1. Refactored string building in scanString()
2. Benchmark comparing current vs array approach

## Technical Details

**Location:** `src/Internal/Lexer.php:131-178`

**Current Issue:**
```php
private function scanString(int $line, int $column): Token
{
    $result = '';
    while (true) {
        // ...
        $result .= $char;        // Repeated string concatenation
        // or
        $result .= $this->parseEscapeSequence();  // More concatenation
    }
}
```

PHP string concatenation (`$result .= $char`) may trigger reallocation when the string outgrows its buffer. For long strings with many characters, this causes O(n) reallocations.

**Proposed Solution:**
```php
private function scanString(int $line, int $column): Token
{
    $parts = [];
    $currentPart = '';

    while (true) {
        $firstByte = $this->buffer->readByte();
        if ($firstByte === null) {
            throw $this->error('Unterminated string', $line, $column);
        }

        if ($firstByte === '"') {
            if ($currentPart !== '') {
                $parts[] = $currentPart;
            }
            return new Token(
                TokenType::STRING,
                count($parts) === 1 ? $parts[0] : implode('', $parts),
                $line, $column
            );
        }

        if ($firstByte === '\\') {
            if ($currentPart !== '') {
                $parts[] = $currentPart;
                $currentPart = '';
            }
            $parts[] = $this->parseEscapeSequence();
            continue;
        }

        // Accumulate in current part
        $currentPart .= $firstByte; // Small concatenations within a segment
    }
}
```

**Note:** This optimization has a larger impact when combined with Task 59 (batch string scanning), where `$currentPart` would be a large `substr()` result rather than individual characters.

**Alternative:** If Task 59 is implemented first, this task may be unnecessary since bulk scanning already avoids per-character concatenation.

## Dependencies
- Best done after or alongside Task 59 (batch string scanning)

## Estimated Complexity
**Low** - Simple refactoring of string construction pattern

## Implementation Notes
- PHP 8+ has optimized single-character concatenation, so the impact may be small for short strings
- The real win comes when combined with batch scanning (Task 59) where segments are longer
- For strings without escape sequences, the best approach is a single `substr()` from the buffer
- Profile to verify this is actually a bottleneck before implementing

## Acceptance Criteria
- [ ] String construction uses efficient accumulation pattern
- [ ] Short strings (< 100 chars) perform no worse than before
- [ ] Long strings (> 10KB) show measurable improvement
- [ ] All existing tests pass
- [ ] PHPStan analysis passes
