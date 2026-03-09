---
title: Optimize skipValue() with Dedicated Fast Path
status: todo
priority: High
description: Add a fast skip mode that counts braces/brackets without full tokenization
---

## Objectives
- Make `skipValue()` significantly faster by avoiding full token parsing
- Count structural characters (`{}[]`) to skip nested structures without creating Token objects
- Reduce cost of JSONPath filtering where most values are skipped

## Deliverables
1. Fast skip implementation that operates at buffer level
2. Benchmarks showing skip throughput improvement
3. All existing tests passing

## Technical Details

**Location:** `src/Internal/Parser.php:408-420` (skipValue), `src/Internal/Parser.php:471-543` (skipArray/skipObject)

**Current Issue:**
`skipValue()` uses the full `nextToken()` pipeline to skip values:
```php
public function skipValue(): void
{
    $token = $this->lexer->nextToken(); // Full tokenization!
    match ($token->type) {
        TokenType::LEFT_BRACE => $this->skipObject(),
        // ...
    };
}
```

When skipping a large nested object, every string key, every string value, every number is fully tokenized (escape sequences decoded, Unicode validated, numbers parsed) even though the result is discarded.

**Proposed Solution:**
Add a buffer-level skip that counts structural depth without full parsing:

```php
public function skipValue(): void
{
    $this->lexer->skipWhitespace();
    $char = $this->lexer->peekByte(); // Raw byte, not token

    if ($char === '{' || $char === '[') {
        $this->skipStructure();
        return;
    }

    if ($char === '"') {
        $this->lexer->skipString(); // Skip without decoding
        return;
    }

    // Numbers, booleans, null - skip raw bytes
    $this->lexer->skipScalar();
}

// In Lexer:
public function skipString(): void
{
    $this->buffer->readByte(); // consume opening "
    while (true) {
        $byte = $this->buffer->readByte();
        if ($byte === null) throw ...;
        if ($byte === '"') return;
        if ($byte === '\\') {
            $this->buffer->readByte(); // skip escaped char
            // Handle \uXXXX (skip 4 more bytes)
        }
    }
}

public function skipStructure(): void
{
    $depth = 0;
    $inString = false;

    while (true) {
        $byte = $this->buffer->readByte();
        if ($byte === null) throw ...;

        if ($inString) {
            if ($byte === '\\') { $this->buffer->readByte(); continue; }
            if ($byte === '"') { $inString = false; }
            continue;
        }

        match ($byte) {
            '{', '[' => $depth++,
            '}', ']' => { if (--$depth === 0) return; },
            '"' => $inString = true,
            default => null,
        };
    }
}
```

**Expected Impact:**
For JSONPath queries like `$.Ads[*]` on a file with many properties per ad object, most properties are skipped. The fast skip avoids:
- String decoding (escape sequences, Unicode)
- Number parsing
- Token object allocation
- Parser state machine overhead

## Dependencies
- Task 57 (buffer optimization) for direct buffer access

## Estimated Complexity
**Medium** - The skip logic is simpler than full parsing; main risk is correctness at boundaries

## Implementation Notes
- Must correctly handle nested strings containing `{`, `}`, `[`, `]`
- Must correctly handle escaped quotes `\"` inside strings
- Must handle `\uXXXX` sequences (4 hex chars after `\u`)
- Does NOT need to validate JSON syntax during skip (it's validated on non-skip paths)
- Depth tracking for `increaseDepth`/`decreaseDepth` still needed for the skip depth limit
- The skip should operate on raw bytes, not UTF-8 characters (structural chars are all ASCII)

**Test Cases:**
- Skip small objects/arrays
- Skip deeply nested structures
- Skip strings containing structural characters
- Skip strings with escape sequences
- Skip at buffer boundaries
- Verify depth limit still enforced

## Acceptance Criteria
- [ ] skipValue() uses dedicated fast-skip for strings and structures
- [ ] No Token objects created during skip
- [ ] No string decoding or number parsing during skip
- [ ] Nested structures with strings containing braces/brackets handled correctly
- [ ] Depth limit still enforced during skip
- [ ] All existing tests pass (598+ tests)
- [ ] PHPStan analysis passes
- [ ] JSONPath benchmark with selective extraction shows >= 25% improvement
