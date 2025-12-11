---
title: Complex Streaming Pattern Coverage
status: todo
priority: Medium
description: Add tests for complex streaming scenarios in Parser
---

## Objectives
- Cover Parser lines 151-152 (nested object match in streaming)
- Cover Parser lines 217-225 (filter evaluation in array streaming)
- Cover Parser lines 242-243 (nested array continuation)
- Improve Parser coverage from 95.0% to 100%

## Deliverables
1. Tests for JSONPath streaming with nested object matches
2. Tests for filter expressions in streaming mode
3. Tests for nested array streaming
4. Parser coverage reaches 100%

## Technical Details

### Current Coverage Gap
- **Parser.php**: 95.0% coverage
- **Missing lines**: 151-152, 217-225, 242-243

### Uncovered Code

#### Lines 151-152: Nested object matches in streaming
```php
private function streamFromObject(): \Generator
{
    // ...
    while (true) {
        // ...
        if ($token->type === TokenType::LEFT_BRACE) {
            // Nested object - recurse or check if it matches
            if ($this->pathEvaluator->matches()) {
                // This object itself matches - parse and yield it
                $value = $this->parseValue();  // Lines 151-152 - NOT COVERED
                yield $value;
            } else {
                // Go deeper to find matches
                yield from $this->streamFromObject();
                $this->pathEvaluator->exitLevel();
            }
        }
        // ...
    }
}
```

**Analysis:** This path is taken when:
1. Using `streamFromObject()` for JSONPath filtering
2. Encountering a nested object
3. The nested object **itself** matches the path (not its children)
4. Need to return the entire object as a match

**Example paths that could trigger this:**
- `$.data.settings` - where `settings` is an entire object we want
- `$.users[*].profile` - where `profile` objects match and should be returned whole

**Action:** ✅ Add test with JSONPath that matches nested objects directly

#### Lines 217-225: Filter expression evaluation in array streaming
```php
private function streamFromArray(): \Generator
{
    // ...
    foreach ($this->lexer as $token) {
        // ...
        if ($needsValue) {
            // Filter expression - need to parse value to evaluate
            $value = $this->parseValue();                         // Lines 217-225
            $this->pathEvaluator->exitLevel();                    // NOT COVERED
            $this->pathEvaluator->enterLevel($index, $value);

            if ($this->pathEvaluator->matches()) {
                yield $value;
            }

            $this->pathEvaluator->exitLevel();
        } else {
            // No filter - check if current position matches structurally
            // ...
        }
    }
}
```

**Analysis:** This handles filter expressions during array streaming like:
- `$[?(@.price > 10)]` - Filter array elements by condition
- `$.items[?(@.active === true)]` - Filter with specific criteria

**Current behavior:** These may use `PathFilter` fallback instead of streaming.

**Action:** ✅ Add test to verify filter expressions in streaming mode

#### Lines 242-243: Nested array continuation after non-match
```php
private function streamFromArray(): \Generator
{
    // ...
    if ($needsValue) {
        // (lines 217-225)
    } else {
        // No filter - check structurally
        if ($this->pathEvaluator->matchesStructure()) {
            // Current position matches
            if ($token->type === TokenType::LEFT_BRACE) {
                yield from $this->streamFromObject();
                $this->pathEvaluator->exitLevel();
            } elseif ($token->type === TokenType::LEFT_BRACKET) {
                yield from $this->streamFromArray();              // Lines 242-243
                $this->pathEvaluator->exitLevel();                // NOT COVERED
            } else {
                // Skip this element
                // ...
            }
        }
    }
}
```

**Analysis:** This handles nested arrays when:
1. Streaming through an array with JSONPath
2. Parent array element doesn't match
3. Element is itself an array that needs to be streamed

**Example:** Path `$.data[*][*]` with structure `{"data": [[1,2], [3,4]]}`

**Action:** ✅ Add test with nested array JSONPath patterns

### Test Scenarios

#### 1. Lines 151-152: Match nested object directly

```php
describe('streaming with nested object matches', function (): void {
    it('yields nested object when path matches the object itself', function (): void {
        $json = <<<JSON
{
    "users": [
        {
            "id": 1,
            "profile": {
                "name": "Alice",
                "age": 30
            }
        },
        {
            "id": 2,
            "profile": {
                "name": "Bob",
                "age": 25
            }
        }
    ]
}
JSON;

        // Path matches the profile object itself, not its properties
        $reader = StreamReader::fromString($json)->withPath('$.users[*].profile');
        $iterator = $reader->readItems();

        $profiles = [];
        foreach ($iterator as $profile) {
            $profiles[] = $profile;
        }

        // Should get two complete profile objects
        expect($profiles)->toHaveCount(2);
        expect($profiles[0])->toBe(['name' => 'Alice', 'age' => 30]);
        expect($profiles[1])->toBe(['name' => 'Bob', 'age' => 25]);
    });

    it('yields nested object at deeper level', function (): void {
        $json = <<<JSON
{
    "data": {
        "items": {
            "settings": {
                "theme": "dark",
                "lang": "en"
            },
            "config": {
                "debug": true
            }
        }
    }
}
JSON;

        // Match the settings object itself
        $reader = StreamReader::fromString($json)->withPath('$.data.items.settings');
        $result = $reader->readAll();

        expect($result)->toHaveCount(1);
        expect($result[0])->toBe(['theme' => 'dark', 'lang' => 'en']);
    });
});
```

#### 2. Lines 217-225: Filter expressions in streaming

**Note:** Current implementation may use `PathFilter` fallback for filter expressions. Need to verify if `streamFromArray` handles filters or always falls back.

```php
describe('filter expressions in streaming mode', function (): void {
    it('evaluates filter expression during array streaming', function (): void {
        $json = <<<JSON
{
    "products": [
        {"id": 1, "price": 10, "active": true},
        {"id": 2, "price": 25, "active": true},
        {"id": 3, "price": 5, "active": false},
        {"id": 4, "price": 30, "active": true}
    ]
}
JSON;

        // Filter with comparison
        $reader = StreamReader::fromString($json)->withPath('$.products[?(@.price > 15)]');
        $iterator = $reader->readItems();

        $results = [];
        foreach ($iterator as $item) {
            $results[] = $item['id'];
        }

        expect($results)->toBe([2, 4]);  // Only items with price > 15
    });

    it('evaluates filter with boolean comparison', function (): void {
        $json = <<<JSON
{
    "items": [
        {"id": 1, "active": true},
        {"id": 2, "active": false},
        {"id": 3, "active": true}
    ]
}
JSON;

        $reader = StreamReader::fromString($json)->withPath('$.items[?(@.active === true)]');
        $iterator = $reader->readItems();

        $results = [];
        foreach ($iterator as $item) {
            $results[] = $item['id'];
        }

        expect($results)->toBe([1, 3]);
    });
});
```

**Important:** Verify if these tests actually trigger lines 217-225 or use PathFilter fallback. May need to check `PathExpression::canUseSimpleStreaming()` logic.

#### 3. Lines 242-243: Nested array streaming

```php
describe('nested array streaming', function (): void {
    it('streams through nested arrays with wildcard', function (): void {
        $json = <<<JSON
{
    "matrix": [
        [1, 2, 3],
        [4, 5, 6],
        [7, 8, 9]
    ]
}
JSON;

        // Path that requires streaming nested arrays
        $reader = StreamReader::fromString($json)->withPath('$.matrix[*][*]');
        $iterator = $reader->readItems();

        $values = [];
        foreach ($iterator as $value) {
            $values[] = $value;
        }

        expect($values)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    });

    it('streams through deeply nested arrays', function (): void {
        $json = <<<JSON
{
    "data": [
        [
            [1, 2],
            [3, 4]
        ],
        [
            [5, 6],
            [7, 8]
        ]
    ]
}
JSON;

        $reader = StreamReader::fromString($json)->withPath('$.data[*][*][*]');
        $iterator = $reader->readItems();

        $values = [];
        foreach ($iterator as $value) {
            $values[] = $value;
        }

        expect($values)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    });

    it('handles mixed nested structures', function (): void {
        $json = <<<JSON
{
    "sections": [
        {
            "items": [1, 2, 3]
        },
        {
            "items": [4, 5]
        }
    ]
}
JSON;

        $reader = StreamReader::fromString($json)->withPath('$.sections[*].items[*]');
        $iterator = $reader->readItems();

        $values = [];
        foreach ($iterator as $value) {
            $values[] = $value;
        }

        expect($values)->toBe([1, 2, 3, 4, 5]);
    });
});
```

## Implementation Steps

1. **Analyze current streaming behavior**
   ```bash
   # Check which paths use streaming vs PathFilter fallback
   grep -n "canUseSimpleStreaming" src/Internal/JsonPath/PathExpression.php
   ```

2. **Add nested object tests** (lines 151-152)
   Start with these as they're most straightforward.

3. **Verify filter streaming** (lines 217-225)
   Determine if filters use streaming or always fall back to PathFilter.

4. **Add nested array tests** (lines 242-243)
   Test various nested array patterns.

5. **Run coverage analysis**
   ```bash
   docker compose run --rm php vendor/bin/pest tests/Integration/JsonPathFilteringTest.php --coverage --min=0
   ```

## Dependencies
- Understanding of JSONPath streaming implementation
- Knowledge of when streaming vs PathFilter is used

## Estimated Complexity
**High** - 3-4 hours. Requires deep understanding of streaming vs fallback behavior.

## Implementation Notes

### Streaming vs PathFilter Decision
From `PathExpression.php`, streaming is used when `canUseSimpleStreaming()` returns true:
```php
public function canUseSimpleStreaming(): bool
{
    // Simple patterns like $.items[*], $.items[5], $.items[1:10]
    // NOT for: recursive descent, wildcards followed by properties, filters
}
```

**This means:**
- Lines 217-225: May NOT be reachable if filters always use PathFilter
- Lines 151-152, 242-243: Should be reachable with simple streaming patterns

### Investigation Required
Before implementing, verify:
1. Does `$.users[*].profile` use streaming? (for lines 151-152)
2. Does `$.products[?(@.price > 10)]` use streaming or PathFilter? (for lines 217-225)
3. Does `$.matrix[*][*]` use streaming? (for lines 242-243)

Run this test to check:
```php
it('debug: check streaming behavior', function (): void {
    $parser = new PathParser();

    $paths = [
        '$.users[*].profile',           // Nested object
        '$.products[?(@.price > 10)]',  // Filter
        '$.matrix[*][*]',               // Nested array
    ];

    foreach ($paths as $path) {
        $expr = $parser->parse($path);
        $canStream = $expr->canUseSimpleStreaming();
        echo "{$path}: " . ($canStream ? 'STREAMING' : 'PATHFILTER') . "\n";
    }
});
```

### If Lines Are Unreachable
If investigation shows these lines are never reached (always use PathFilter fallback), then:
- Mark as `@codeCoverageIgnore`
- Document why they exist (future optimization or legacy code)
- Consider refactoring to remove dead code

## Acceptance Criteria
- [x] Investigated which JSONPath patterns use streaming vs PathFilter
- [x] Tests added for nested object matches (lines 151-152) if reachable
- [x] Tests added for filter expressions (lines 217-225) if reachable
- [x] Tests added for nested arrays (lines 242-243) if reachable
- [x] Unreachable code marked with `@codeCoverageIgnore` if applicable
- [x] All new tests pass
- [x] Parser coverage improves or code is documented
- [x] Code follows project conventions

## Success Metrics
After completion:
- Parser: 95.0% → 100% (if lines are reachable) ✅
- Or: Parser: 95.0% → 95.0% (with lines marked as ignore) ✅
- **Expected Coverage Gain:** +0.3% (if reachable) or documented (if not)
- **Overall Project Coverage:** 98.2% → 98.5% (if reachable)

## Notes

### Why This Is Medium Priority
- **High effort**: Requires understanding of complex streaming logic
- **Uncertain value**: Lines may not be reachable with current implementation
- **Alternative**: May be dead code from earlier implementation

### When to Skip This Task
Skip if:
- Investigation shows these lines are unreachable
- Current implementation always uses PathFilter for these patterns
- Time budget doesn't allow for complex analysis

In that case:
- Mark lines with `@codeCoverageIgnore`
- Document as legacy or future optimization code
- Accept current coverage level

### Value if Reachable
If these lines ARE reachable:
- **High value**: Tests core streaming functionality
- **Critical**: Ensures streaming works for complex patterns
- **Worth effort**: Core feature testing
