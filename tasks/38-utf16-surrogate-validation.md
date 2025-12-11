---
title: Add UTF-16 Lone Surrogate Validation
status: todo
priority: High
description: Validate and reject lone UTF-16 surrogates to prevent invalid UTF-8 output
---

## Objectives
- Detect and reject lone high surrogates (0xD800-0xDBFF) in Unicode escape sequences
- Detect and reject lone low surrogates (0xDC00-0xDFFF)
- Ensure all Unicode escape sequences produce valid UTF-8 output
- Provide clear error messages for invalid surrogate pairs

## Deliverables
1. Enhanced `parseUnicodeEscape()` method in `Lexer` with surrogate validation
2. Proper error handling for incomplete or invalid surrogate pairs
3. Unit tests covering all surrogate pair edge cases
4. Documentation of RFC compliance for Unicode handling

## Technical Details

**Location:** `src/Internal/Lexer.php:269-306`

**Current Issue:**
```php
private function parseUnicodeEscape(): string
{
    // ...
    // Handle UTF-16 surrogate pairs (high surrogate: 0xD800-0xDBFF)
    if ($codepoint >= 0xD800 && $codepoint <= 0xDBFF) {
        if ($this->buffer->peek() === '\\' && $this->buffer->peek(1) === 'u') {
            // ... reads low surrogate
        }
        // If low surrogate not found or invalid, falls through with invalid codepoint
    }

    return mb_chr((int) $codepoint, 'UTF-8'); // May produce invalid UTF-8
}
```

**Proposed Solution:**
```php
private function parseUnicodeEscape(): string
{
    $codepoint = $this->parseHexDigits(4);

    // Handle UTF-16 surrogate pairs
    if ($codepoint >= 0xD800 && $codepoint <= 0xDBFF) {
        // High surrogate - must be followed by low surrogate
        if ($this->buffer->peek() !== '\\' || $this->buffer->peek(1) !== 'u') {
            throw $this->error(
                'Invalid lone high UTF-16 surrogate',
                ParseException::INVALID_UNICODE_ESCAPE
            );
        }

        // Read low surrogate
        $this->buffer->advance(2); // Skip \u
        $lowSurrogate = $this->parseHexDigits(4);

        if ($lowSurrogate < 0xDC00 || $lowSurrogate > 0xDFFF) {
            throw $this->error(
                'Invalid UTF-16 surrogate pair - expected low surrogate',
                ParseException::INVALID_UNICODE_ESCAPE
            );
        }

        // Combine surrogates into codepoint
        $codepoint = 0x10000 + (($codepoint - 0xD800) << 10) + ($lowSurrogate - 0xDC00);
    } elseif ($codepoint >= 0xDC00 && $codepoint <= 0xDFFF) {
        // Lone low surrogate
        throw $this->error(
            'Invalid lone low UTF-16 surrogate',
            ParseException::INVALID_UNICODE_ESCAPE
        );
    }

    $char = mb_chr((int) $codepoint, 'UTF-8');
    if ($char === false) {
        throw $this->error(
            'Invalid Unicode codepoint',
            ParseException::INVALID_UNICODE_ESCAPE
        );
    }

    return $char;
}
```

## Dependencies
- None (high priority item)

## Estimated Complexity
**Low** - Straightforward validation logic with clear RFC specification

## Implementation Notes
- UTF-16 surrogate range: 0xD800-0xDFFF
  - High surrogates: 0xD800-0xDBFF
  - Low surrogates: 0xDC00-0xDFFF
- Valid surrogates must come in pairs (high followed by low)
- Lone surrogates are explicitly invalid per RFC 8259
- Formula to combine surrogates: `0x10000 + ((high - 0xD800) << 10) + (low - 0xDC00)`
- May need to add `INVALID_UNICODE_ESCAPE` constant to `ParseException` if not present

## Acceptance Criteria
- [ ] Lone high surrogates (0xD800-0xDBFF) throw ParseException
- [ ] Lone low surrogates (0xDC00-0xDFFF) throw ParseException
- [ ] Valid surrogate pairs are correctly combined into codepoints
- [ ] Invalid codepoints from mb_chr() are caught and handled
- [ ] Tests cover all edge cases: lone high, lone low, valid pairs, invalid pairs
- [ ] Tests verify correct UTF-8 output for valid surrogate pairs
- [ ] Error messages clearly indicate the type of surrogate error
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
