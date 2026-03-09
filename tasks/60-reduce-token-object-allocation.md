---
title: Reduce Token Object Allocation
status: todo
priority: High
description: Minimize Token object creation by reusing structural tokens and avoiding allocation for skipped values
---

## Objectives
- Reduce the number of Token object allocations during parsing
- Reuse singleton Token instances for structural tokens (`{`, `}`, `[`, `]`, `:`, `,`)
- Avoid creating Token objects when skipping values

## Deliverables
1. Singleton Token pool for structural tokens
2. Skip-aware tokenization that avoids unnecessary allocation
3. Benchmarks showing reduced allocation overhead

## Technical Details

**Location:** `src/Internal/Lexer.php:84-97`, `src/Internal/Token.php`, `src/Internal/Parser.php:408-420`

**Current Issue:**
Every token creates a new `Token` object with `new Token(...)`. For a JSON array of 1000 objects with 5 properties each:
- 1000 `[` + 1000 `]` = 2000 structural tokens (arrays don't have these, but objects do)
- 5000 string keys + 5000 colons + 4000 commas = 14,000 structural tokens
- Plus value tokens

Each Token allocation involves:
1. Memory allocation for the object
2. Constructor call with 4 parameters
3. GC pressure from short-lived objects

**Proposed Solution:**

### 1. Singleton structural tokens (no position tracking needed for non-error paths)
```php
final class Lexer
{
    // Pre-allocated structural tokens (position = 0,0 since it's only used in error paths)
    private static ?Token $leftBrace = null;
    private static ?Token $rightBrace = null;
    // ... etc

    private static function getStructuralToken(TokenType $type): Token
    {
        return match ($type) {
            TokenType::LEFT_BRACE => self::$leftBrace ??= new Token(TokenType::LEFT_BRACE, null, 0, 0),
            TokenType::RIGHT_BRACE => self::$rightBrace ??= new Token(TokenType::RIGHT_BRACE, null, 0, 0),
            // ...
        };
    }
}
```

### 2. Position-on-demand for structural tokens
Since structural token positions are only used in error messages (which are rare), we can defer position tracking:

```php
// Option A: Store position in Lexer, not Token
// When Parser needs position for error, ask Lexer

// Option B: Lightweight token without position
final class LightToken
{
    public function __construct(
        public readonly TokenType $type,
        public readonly mixed $value = null,
    ) {}
}
```

### 3. Skip optimization in Parser
When `skipValue()` is called, the Lexer still creates full Token objects that are immediately discarded. A skip-mode flag could tell the Lexer to return minimal tokens:

```php
// Parser skipValue could use a lighter scanning mode
public function skipValue(): void
{
    $type = $this->lexer->skipAndReturnType(); // Returns TokenType only, no Token allocation
    match ($type) {
        TokenType::LEFT_BRACE => $this->skipObject(),
        TokenType::LEFT_BRACKET => $this->skipArray(),
        // scalars already consumed
    };
}
```

## Dependencies
- Task 57 (buffer optimization) ideally done first

## Estimated Complexity
**Medium** - Requires API changes to Token/Lexer but logic stays the same

## Implementation Notes
- The `Token` class is `@internal` so its API can change freely
- Position tracking is only needed for error messages (ParseException)
- Consider using a simple array `[TokenType, mixed]` tuple instead of objects for hot path
- Must preserve error message quality (position info) when errors do occur
- Profile to verify that Token allocation is actually significant (vs buffer I/O)

**Risk:** PHP's object allocation is relatively fast. This optimization may yield smaller gains than buffer I/O optimizations. Measure first.

**Test Cases:**
- All existing tests pass (Token changes are internal)
- Error messages still contain correct line/column
- skipValue still works correctly
- Memory usage does not increase

## Acceptance Criteria
- [ ] Structural tokens reuse pre-allocated instances where possible
- [ ] skipValue() path minimizes object allocation
- [ ] Error messages still contain accurate position information
- [ ] All existing tests pass (598+ tests)
- [ ] PHPStan analysis passes
- [ ] Benchmark shows measurable reduction in allocation count
- [ ] No increase in memory usage
