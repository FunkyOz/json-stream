---
title: Remove or Implement Unused Config Constants
status: todo
priority: Low
description: Review and remove unused Config constants or implement their functionality
---

## Objectives
- Review all constants marked as "reserved for future implementation"
- Decide whether to implement features or remove constants
- Prevent confusion from unused constants in public API
- Clean up Config class for v1.0 release

## Deliverables
1. Decision document on each unused constant (implement vs remove)
2. Removed constants or implemented features
3. Updated documentation
4. Tests for any newly implemented features

## Technical Details

**Location:** `src/Config.php:71-121`

**Unused Constants:**
```php
/**
 * Relaxed parsing mode (reserved for future implementation)
 * Allows non-standard JSON features like:
 * - Single-quoted strings
 * - Unquoted property names
 * - Trailing commas
 * - Comments
 */
public const MODE_RELAXED = 0b0010;

/**
 * Numeric check encoding (reserved for future implementation)
 */
public const ENCODE_NUMERIC_CHECK = 0b0001;

/**
 * Pretty print encoding (reserved for future implementation)
 */
public const ENCODE_PRETTY_PRINT = 0b0010;

/**
 * Unescaped slashes encoding (reserved for future implementation)
 */
public const ENCODE_UNESCAPED_SLASHES = 0b0100;

/**
 * Unescaped unicode encoding (reserved for future implementation)
 */
public const ENCODE_UNESCAPED_UNICODE = 0b1000;
```

## Decision Framework

For each constant, consider:

### 1. MODE_RELAXED
**Options:**
- **Remove:** v1.0 focuses on strict RFC 8259 compliance
- **Implement:** Add relaxed parsing mode supporting JSON5-like features
- **Keep:** Reserve for future v2.0

**Recommendation:** Remove for v1.0, can add in v2.0 if needed
**Rationale:** Strict RFC compliance is clearer for v1.0, feature can be added without breaking changes

### 2. ENCODE_* Constants
**Options:**
- **Remove:** Writer functionality was removed in Task 26
- **Keep:** May be useful if writer is re-added in future
- **Document:** Mark as deprecated or future-only

**Recommendation:** Remove all ENCODE_* constants
**Rationale:** Writer was removed from v1.0 scope, these are not needed

## Proposed Changes

**Config.php:**
```php
// Remove unused constants:
// - MODE_RELAXED
// - ENCODE_NUMERIC_CHECK
// - ENCODE_PRETTY_PRINT
// - ENCODE_UNESCAPED_SLASHES
// - ENCODE_UNESCAPED_UNICODE

// Keep only actively used constants:
public const MODE_STRICT = 0b0001;  // If used
public const MAX_DEPTH = 512;       // If used
// etc.
```

**CHANGELOG.md:**
```markdown
## [1.0.0] - 2025-XX-XX

### Removed
- Removed unused `MODE_RELAXED` constant (may return in v2.0)
- Removed encoder constants (ENCODE_*) as writer functionality is not in v1.0
```

## Dependencies
- Related to Task 26 (writer removal)

## Estimated Complexity
**Low** - Simple removal unless features are implemented

## Implementation Notes
- Search codebase to confirm constants are truly unused:
  ```bash
  grep -r "MODE_RELAXED" src/ tests/
  grep -r "ENCODE_" src/ tests/
  ```
- Check if any documentation references these constants
- Consider semantic versioning implications
- Removing unused public constants is not a breaking change if never used
- If keeping any constants, add `@deprecated` annotation

**Alternative: Deprecation Path**
```php
/**
 * @deprecated Will be removed in v2.0 - not implemented in v1.x
 */
public const MODE_RELAXED = 0b0010;
```

**Implementation Priority:**
If implementing any features, prioritize:
1. MODE_RELAXED - High value, widely used in other parsers
2. ENCODE_PRETTY_PRINT - Useful for writer if re-added
3. Others - Lower priority

## Acceptance Criteria
- [ ] All unused constants have been reviewed
- [ ] Decision made for each: remove, implement, or deprecate
- [ ] If removed: constants deleted from Config.php
- [ ] If implemented: features fully functional with tests
- [ ] If deprecated: proper @deprecated annotations added
- [ ] Search confirms no usage of removed constants
- [ ] Documentation updated to reflect changes
- [ ] CHANGELOG.md documents removals
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
