---
title: Review and Clean Up PHPStan Ignore Comments
status: todo
priority: Low
description: Review all PHPStan ignore comments and refactor code to eliminate unnecessary suppressions
---

## Objectives
- Review all `@phpstan-ignore` comments in the codebase
- Determine if each suppression is truly necessary
- Refactor code to satisfy type checking where possible
- Document why suppressions are needed where they remain
- Improve type safety and code quality

## Deliverables
1. Documented review of each PHPStan ignore comment
2. Refactored code eliminating unnecessary suppressions
3. Better type annotations where applicable
4. Updated comments explaining remaining suppressions
5. Report of findings and changes made

## Technical Details

**Affected Files:**
- `src/Internal/Parser.php`
- `src/Reader/ObjectIterator.php`
- `src/Reader/ItemIterator.php`
- Others (to be discovered)

**Common Patterns:**
```php
// @phpstan-ignore identical.alwaysFalse
if ($char === null) {
    // Type system thinks this can never be null, but it can
}
```

**Review Questions for Each Suppression:**
1. Is this check truly necessary for runtime safety?
2. Can type annotations be improved to make check valid?
3. Can code be restructured to avoid the issue?
4. Is this dead code that can be removed?
5. Is the suppression masking a real bug?

**Proposed Approach:**

1. **Catalog All Suppressions:**
```bash
grep -r "@phpstan-ignore" src/
```

2. **Categorize Each:**
- **Type A:** False positive - type system limitation
- **Type B:** Defensive programming - runtime safety
- **Type C:** Dead code - can be removed
- **Type D:** Fixable - code can be refactored

3. **Handle Each Category:**

**Type A Example (Keep with documentation):**
```php
/**
 * PHPStan cannot detect that buffer may return null when stream ends.
 * This is a valid runtime edge case.
 * @phpstan-ignore identical.alwaysFalse
 */
if ($char === null) {
    throw new ParseException('Unexpected end of stream');
}
```

**Type B Example (Use assertion):**
```php
// Instead of:
// @phpstan-ignore identical.alwaysFalse
if ($char === null) {

// Consider:
assert($char !== null, 'Character should not be null at this point');
// or add proper type narrowing
```

**Type C Example (Remove):**
```php
// If analysis is correct that check is always false, remove it:
// @phpstan-ignore identical.alwaysFalse
// if ($char === null) {  // <-- Delete this dead code
//     // ...
// }
```

**Type D Example (Refactor):**
```php
// Instead of:
function process(mixed $value): void {
    // @phpstan-ignore argument.type
    $this->helper($value);
}

// Fix with proper type:
function process(mixed $value): void {
    if (is_string($value)) {
        $this->helper($value); // Now type-safe
    }
}
```

## Dependencies
- None (independent code quality task)

## Estimated Complexity
**Low to Medium** - Depends on number and complexity of suppressions

## Implementation Notes
- Review each suppression individually - don't batch blindly
- Some suppressions may be masking bugs - test thoroughly after changes
- Consider whether stricter PHPStan level is appropriate
- May reveal opportunities to improve type declarations
- Document decisions for future maintainers

**PHPStan Configuration:**
Check current PHPStan level in `phpstan.neon.dist`:
```neon
parameters:
    level: max  # or specific level
```

**Common Suppression Reasons:**
- `identical.alwaysFalse` - Type system can't prove condition is possible
- `argument.type` - Mixed type passed to typed parameter
- `return.type` - Return type doesn't match declared type
- `property.notFound` - Dynamic property access

**Tools:**
```bash
# Find all suppressions
grep -r "@phpstan-ignore" src/ | wc -l

# Run PHPStan to see what happens without suppressions
phpstan analyse src/ --level=max

# Check specific error types
grep -r "@phpstan-ignore identical" src/
```

## Acceptance Criteria
- [ ] All `@phpstan-ignore` comments have been reviewed
- [ ] Unnecessary suppressions have been removed
- [ ] Code has been refactored where possible to avoid suppressions
- [ ] Remaining suppressions have clear documentation explaining why they're needed
- [ ] Tests verify behavior is correct for refactored code
- [ ] PHPStan analysis still passes at max level
- [ ] Document summarizes findings and changes
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
