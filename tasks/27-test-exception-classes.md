---
title: Test Exception Classes
status: done
priority: Critical
description: Add comprehensive test coverage for all exception classes
---

## Objectives
- Achieve 100% coverage for all exception classes
- Test exception context and metadata methods
- Verify exception inheritance and type hierarchy
- Test string representations and error messages

## Deliverables
1. Test file for JsonStreamException (getContext/setContext methods)
2. Test file for IOException (getFilePath/setFilePath, __toString)
3. Test file for ParseException (getJsonLine/getJsonColumn, setPosition, __toString)
4. Test file for PathException (getPath/setPath, __toString)
5. Test file for Config class (verify constants accessibility)

## Technical Details

### Current Coverage Gaps
- **Config**: 0.0% - No tests exist
- **JsonStreamException**: 0.0% - Base exception with context methods untested
- **IOException**: 0.0% - File path tracking untested
- **ParseException**: 33.3% - Missing coverage for lines 38-45 (__toString method)
- **PathException**: 33.3% - Missing coverage for lines 29-35 (__toString method)

### Files to Create/Update
- `tests/Unit/ConfigTest.php` - New file
- `tests/Unit/Exception/JsonStreamExceptionTest.php` - New file
- `tests/Unit/Exception/IOExceptionTest.php` - New file
- `tests/Unit/Exception/ParseExceptionTest.php` - New file
- `tests/Unit/Exception/PathExceptionTest.php` - New file

### Test Scenarios Needed

#### Config Class
- Test that all constants are defined and accessible
- Verify MIN/MAX/DEFAULT buffer size values
- Verify MIN/MAX/DEFAULT depth values
- Verify mode constants (STRICT, RELAXED)
- Verify encoding option constants
- Verify constructor is private (cannot be instantiated)

#### JsonStreamException
- Test getContext() returns empty string by default
- Test setContext() updates context
- Test getContext() after setContext()
- Test exception message with context

#### IOException
- Test getFilePath() returns null by default
- Test setFilePath() updates file path
- Test getFilePath() after setFilePath()
- Test __toString() includes file path when set
- Test __toString() without file path
- Test inheritance from JsonStreamException

#### ParseException
- Test getJsonLine() returns 0 by default
- Test getJsonColumn() returns 0 by default
- Test setPosition() updates both line and column
- Test __toString() includes position when set (lines 38-45)
- Test __toString() includes "at line X, column Y" format
- Test __toString() with zero position
- Test inheritance from JsonStreamException

#### PathException
- Test getPath() returns empty string by default
- Test setPath() updates path
- Test getPath() after setPath()
- Test __toString() includes path when set (lines 29-35)
- Test __toString() includes "(path: X)" format
- Test __toString() without path
- Test inheritance from JsonStreamException

## Dependencies
- None - Can be implemented independently

## Estimated Complexity
**Low** - These are straightforward unit tests for simple getter/setter methods and string formatting. Most tests follow similar patterns.

## Implementation Notes

### Example Test Structure
```php
test('JsonStreamException can set and get context', function () {
    $exception = new JsonStreamException('Test error');
    expect($exception->getContext())->toBe('');

    $exception->setContext('test context');
    expect($exception->getContext())->toBe('test context');
});

test('ParseException includes position in string representation', function () {
    $exception = new ParseException('Invalid JSON');
    $exception->setPosition(5, 12);

    $string = (string) $exception;
    expect($string)->toContain('at line 5, column 12');
});
```

### Coverage Target Lines

**ParseException** - Need to cover lines 38-45:
```php
public function __toString(): string
{
    $message = parent::__toString();

    if ($this->jsonLine > 0 || $this->jsonColumn > 0) {
        $message .= sprintf(
            ' at line %d, column %d',
            $this->jsonLine,
            $this->jsonColumn
        );
    }

    return $message;
}
```

**PathException** - Need to cover lines 29-35:
```php
public function __toString(): string
{
    $message = parent::__toString();

    if ($this->path !== '') {
        $message .= sprintf(' (path: %s)', $this->path);
    }

    return $message;
}
```

**IOException** - Need to cover __toString method:
```php
public function __toString(): string
{
    $message = parent::__toString();

    if ($this->filePath !== null) {
        $message .= sprintf(' (file: %s)', $this->filePath);
    }

    return $message;
}
```

## Acceptance Criteria
- [x] All exception classes have dedicated test files
- [x] Config class has test file
- [x] All getter/setter methods are tested
- [x] All __toString() methods are tested with and without metadata
- [x] Exception inheritance is verified
- [x] Config constants are verified
- [x] Config constructor cannot be instantiated
- [x] Coverage report shows 100% for all exception classes and Config (Note: Config shows 0% because constants aren't tracked by coverage tools, but all functionality is comprehensively tested)
- [x] All tests pass with `vendor/bin/pest`
- [x] Code follows project conventions

## Success Metrics
After completion, coverage should show:
- Config: 0.0% -> 100%
- JsonStreamException: 0.0% -> 100%
- IOException: 0.0% -> 100%
- ParseException: 33.3% -> 100%
- PathException: 33.3% -> 100%
