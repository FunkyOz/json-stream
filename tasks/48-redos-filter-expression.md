---
title: Prevent ReDoS in Filter Expression Parsing
status: todo
priority: Medium
description: Add input validation to prevent ReDoS attacks in filter expression regex
---

## Objectives
- Add input length validation before regex matching
- Review regex pattern for potential ReDoS vulnerabilities
- Use more restrictive pattern or limit backtracking
- Add tests for pathological inputs
- Document input limitations

## Deliverables
1. Modified `FilterSegment` with input length validation
2. Optimized or more restrictive regex pattern
3. Tests with long inputs and pathological cases
4. Documentation of filter expression limits

## Technical Details

**Location:** `src/Internal/JsonPath/FilterSegment.php:51`

**Current Issue:**
```php
if (preg_match('/^@\\.([\\w.]+)\\s*([<>=!]+)\\s*(.+)$/', $expr, $matches)) {
    // The .+ at the end is greedy and could cause backtracking
}
```

**Vulnerability:**
- The `.+` pattern is greedy and matches any character
- With long input and failing match, excessive backtracking can occur
- Example pathological input: `@.property = ` + "a" repeated 10000 times + "!"

**Proposed Solution (Option 1: Input Length Limit):**
```php
private const MAX_FILTER_EXPRESSION_LENGTH = 1000;

public function __construct(string $expression)
{
    if (strlen($expression) > self::MAX_FILTER_EXPRESSION_LENGTH) {
        throw new PathException(
            sprintf(
                'Filter expression too long (max %d characters)',
                self::MAX_FILTER_EXPRESSION_LENGTH
            )
        );
    }

    if (preg_match('/^@\\.([\\w.]+)\\s*([<>=!]+)\\s*(.+)$/', $expression, $matches)) {
        // ... rest of parsing
    }
}
```

**Proposed Solution (Option 2: More Restrictive Pattern):**
```php
// Instead of .+ (any character, greedy), use more specific pattern
// For string values: quoted strings
// For numbers: digit patterns
// For booleans: true|false
// For null: null

$pattern = '/^@\\.([\\w.]+)\\s*([<>=!]+)\\s*(' .
    '"[^"]*"|' .        // double-quoted string
    "'[^']*'|" .        // single-quoted string
    '\\d+\\.?\\d*|' .   // number
    'true|false|null' . // boolean or null
    ')$/';

if (preg_match($pattern, $expression, $matches)) {
    // More restrictive pattern prevents backtracking
}
```

**Proposed Solution (Option 3: Combined):**
```php
private const MAX_FILTER_EXPRESSION_LENGTH = 1000;
private const MAX_VALUE_LENGTH = 500;

public function __construct(string $expression)
{
    // Length check first (fast rejection)
    if (strlen($expression) > self::MAX_FILTER_EXPRESSION_LENGTH) {
        throw new PathException('Filter expression too long');
    }

    // Use restrictive pattern with length limits
    $pattern = '/^@\\.([\\w.]{1,100})\\s*([<>=!]{1,3})\\s*(' .
        '"[^"]{0,' . self::MAX_VALUE_LENGTH . '}"|' .
        "'[^']{0," . self::MAX_VALUE_LENGTH . "}'" .
        '|\\d{1,20}(?:\\.\\d{1,20})?' .
        '|true|false|null' .
        ')$/';

    if (preg_match($pattern, $expression, $matches)) {
        $this->property = $matches[1];
        $this->operator = $matches[2];
        $this->value = $this->parseValue($matches[3]);
    } else {
        throw new PathException("Invalid filter expression: {$expression}");
    }
}
```

## Dependencies
- None

## Estimated Complexity
**Low** - Simple validation and pattern improvement

## Implementation Notes
- ReDoS (Regular Expression Denial of Service) is a real security concern
- Even if JSONPath expressions are typically short, defense in depth is good practice
- Current regex has low risk due to simple pattern, but improvement is worthwhile
- Consider using `preg_match()` with timeout if available (PCRE2)
- Test with large inputs to measure performance

**Risk Assessment:**
- **Current risk:** Low (JSONPath expressions typically user-controlled and short)
- **Impact if exploited:** Medium (could cause request timeouts)
- **Mitigation priority:** Medium (good security practice)

**Test Cases:**
```php
// Valid expressions
'@.price > 10'
'@.name == "test"'
'@.active != false'

// Pathological inputs
'@.property = ' . str_repeat('a', 10000)  // Long value
'@.' . str_repeat('a', 1000) . ' > 10'    // Long property
'@.prop ' . str_repeat('=', 1000) . ' 10' // Long operator

// Edge cases
''  // empty
str_repeat('a', 10000)  // just long string
```

**Benchmarking:**
```php
// Measure regex performance
$start = microtime(true);
preg_match($pattern, $input);
$duration = microtime(true) - $start;

// Should be < 1ms even for pathological inputs
```

## Acceptance Criteria
- [ ] Input length validation added (max 1000 characters recommended)
- [ ] Regex pattern reviewed and optimized if needed
- [ ] Tests verify rejection of overly long inputs
- [ ] Tests verify normal inputs still work
- [ ] Benchmark confirms no ReDoS vulnerability
- [ ] Documentation explains filter expression limits
- [ ] Error messages clearly state the limit
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
