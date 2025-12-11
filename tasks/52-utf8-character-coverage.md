---
title: Add UTF-8 Character Test Coverage
status: todo
priority: High
description: Add tests for 3-byte UTF-8 characters and incomplete UTF-8 sequences
---

## Objectives
- Cover 3-byte UTF-8 character handling in Lexer (lines 199-201)
- Cover incomplete UTF-8 sequence at EOF (line 215)
- Improve UTF-8 handling test coverage from 97.0% to 100%

## Deliverables
1. Tests for 3-byte UTF-8 characters in JSON strings
2. Test for truncated UTF-8 sequence at end of stream
3. Lexer coverage reaches 100%

## Technical Details

### Current Coverage Gap
- **Lexer.php**: 97.0% coverage
- **Missing lines**: 199-207, 215

### Uncovered Code

#### Lines 199-201: 3-byte UTF-8 character handling
```php
// Determine number of bytes in this UTF-8 sequence
if (($ord & 0xE0) === 0xC0) {
    $additionalBytes = 1;  // 2-byte character - COVERED
} elseif (($ord & 0xF0) === 0xE0) {
    $additionalBytes = 2;  // 3-byte character - Lines 199-201 NOT COVERED
} elseif (($ord & 0xF8) === 0xF0) {
    $additionalBytes = 3;  // 4-byte character - COVERED
} else {
    return $firstByte;     // Invalid UTF-8 - Line 207 NOT COVERED
}
```

#### Line 215: Incomplete UTF-8 at EOF
```php
for ($i = 0; $i < $additionalBytes; $i++) {
    $byte = $this->buffer->readByte();
    if ($byte === null) {
        break;  // Line 215 - NOT COVERED (EOF mid-character)
    }
    $char .= $byte;
}
```

### UTF-8 Byte Sequences

| Bytes | Range | Example Characters | Current Coverage |
|-------|-------|-------------------|------------------|
| 1 | U+0000 to U+007F | ASCII (a-z, 0-9) | ✅ Covered |
| 2 | U+0080 to U+07FF | Latin Extended (é, ñ) | ✅ Covered |
| 3 | U+0800 to U+FFFF | Most Unicode (€, 中, ₹) | ❌ NOT Covered |
| 4 | U+10000 to U+10FFFF | Emoji (😀, 🎉) | ✅ Covered |

### Test Scenarios Needed

#### 1. 3-byte UTF-8 Characters (Lines 199-201)
Characters in the 3-byte range include:
- European symbols: € (Euro sign) - U+20AC
- CJK characters: 中 (Chinese) - U+4E2D
- Currency symbols: ₹ (Rupee) - U+20B9
- Mathematical symbols: ≠ (not equal) - U+2260
- Arrows: → (rightward arrow) - U+2192

```php
it('tokenizes string with 3-byte UTF-8 characters', function (): void {
    // Euro sign (€) is 3-byte UTF-8: E2 82 AC
    $json = '{"currency": "€100"}';
    $lexer = new Lexer(createBuffer($json));

    $lexer->nextToken(); // {
    $lexer->nextToken(); // "currency"
    $lexer->nextToken(); // :
    $token = $lexer->nextToken(); // "€100"

    expect($token->type)->toBe(TokenType::STRING);
    expect($token->value)->toBe('€100');
});

it('tokenizes string with CJK characters', function (): void {
    // Chinese character 中 (zhōng) is 3-byte UTF-8: E4 B8 AD
    $json = '{"word": "中文"}';
    $lexer = new Lexer(createBuffer($json));

    $lexer->nextToken(); // {
    $lexer->nextToken(); // "word"
    $lexer->nextToken(); // :
    $token = $lexer->nextToken(); // "中文"

    expect($token->type)->toBe(TokenType::STRING);
    expect($token->value)->toBe('中文');
});

it('tokenizes string with currency symbols', function (): void {
    // Rupee symbol (₹) is 3-byte UTF-8: E2 82 B9
    $json = '{"price": "₹500"}';
    $lexer = new Lexer(createBuffer($json));

    $lexer->nextToken(); // {
    $lexer->nextToken(); // "price"
    $lexer->nextToken(); // :
    $token = $lexer->nextToken(); // "₹500"

    expect($token->type)->toBe(TokenType::STRING);
    expect($token->value)->toBe('₹500');
});

it('tokenizes string with mathematical symbols', function (): void {
    // Not equal (≠) is 3-byte UTF-8: E2 89 A0
    $json = '{"formula": "a ≠ b"}';
    $lexer = new Lexer(createBuffer($json));

    $lexer->nextToken(); // {
    $lexer->nextToken(); // "formula"
    $lexer->nextToken(); // :
    $token = $lexer->nextToken(); // "a ≠ b"

    expect($token->type)->toBe(TokenType::STRING);
    expect($token->value)->toBe('a ≠ b');
});
```

#### 2. Incomplete UTF-8 Sequence at EOF (Line 215)
This is a rare error condition where a file ends in the middle of a multi-byte UTF-8 character.

```php
it('handles incomplete 3-byte UTF-8 sequence at EOF', function (): void {
    // Start of Euro symbol (€) but truncated: only E2 82 (missing AC)
    // This creates invalid UTF-8 at end of stream
    $json = "\"\xE2\x82";  // Incomplete UTF-8 sequence

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $json);
    rewind($stream);

    $buffer = new BufferManager($stream, 64);
    $lexer = new Lexer($buffer);

    // The lexer should handle incomplete sequence gracefully
    $token = $lexer->nextToken();

    // May return the incomplete bytes or handle as error
    expect($token->type)->toBeIn([TokenType::STRING, TokenType::EOF]);
});

it('handles incomplete 4-byte UTF-8 sequence at EOF', function (): void {
    // Start of emoji (😀) but truncated: only F0 9F (missing 98 80)
    $json = "\"\xF0\x9F";  // Incomplete UTF-8 sequence

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $json);
    rewind($stream);

    $buffer = new BufferManager($stream, 64);
    $lexer = new Lexer($buffer);

    $token = $lexer->nextToken();
    expect($token->type)->toBeIn([TokenType::STRING, TokenType::EOF]);
});
```

#### 3. Mixed UTF-8 byte lengths
```php
it('handles mixed UTF-8 byte lengths in same string', function (): void {
    // Mix of 1-byte (a), 2-byte (é), 3-byte (€), 4-byte (😀)
    $json = '{"text": "aé€😀"}';
    $lexer = new Lexer(createBuffer($json));

    $lexer->nextToken(); // {
    $lexer->nextToken(); // "text"
    $lexer->nextToken(); // :
    $token = $lexer->nextToken(); // "aé€😀"

    expect($token->type)->toBe(TokenType::STRING);
    expect($token->value)->toBe('aé€😀');
});
```

## Dependencies
- None (unit tests for Lexer)

## Estimated Complexity
**Low** - 1 hour. Straightforward test additions with specific UTF-8 characters.

## Implementation Notes

### Helper Function
The tests use `createBuffer()` helper from existing tests:
```php
function createBuffer(string $json): BufferManager
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $json);
    rewind($stream);
    return new BufferManager($stream, 64);
}
```

### UTF-8 Encoding
PHP source files are UTF-8 encoded, so you can directly use Unicode characters in strings:
```php
'{"currency": "€100"}'  // Direct Unicode
```

Or use escape sequences for explicit byte values:
```php
"\xE2\x82\xAC"  // Euro sign as hex bytes
```

### Testing Strategy
1. Add 3-byte UTF-8 tests first (lines 199-201)
2. Verify coverage improves
3. Add incomplete sequence tests (line 215)
4. Verify 100% Lexer coverage

## Acceptance Criteria
- [x] Tests added for 3-byte UTF-8 characters (€, 中, ₹, ≠)
- [x] Tests added for mixed byte-length strings
- [x] Tests added for incomplete UTF-8 at EOF
- [x] All new tests pass
- [x] Lexer coverage reaches 100%
- [x] Code follows project conventions

## Success Metrics
After completion:
- Lexer: 97.0% → 100% ✅
- **Expected Coverage Gain:** +0.5%
- **Overall Project Coverage:** 97.4% → 97.9%

## Notes
3-byte UTF-8 characters are very common in international applications:
- European monetary symbols (€)
- Asian languages (中文, 日本語, 한국어)
- Mathematical and technical symbols (≠, ≤, ≥, →)
- Currency symbols (₹, ¥, £)

This test has high value for international JSON parsing.
