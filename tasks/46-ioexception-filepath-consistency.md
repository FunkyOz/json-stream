---
title: Set File Path Consistently in IOException
status: todo
priority: Low
description: Use setFilePath() method consistently when throwing IOException
---

## Objectives
- Identify all places where IOException is thrown
- Ensure `setFilePath()` is called for all file-related errors
- Provide structured error information for better debugging
- Maintain consistency across the codebase

## Deliverables
1. Updated `StreamReader::fromFile()` and other file operations to use `setFilePath()`
2. Review of all IOException throw sites
3. Unit tests verifying file path is set in exceptions
4. Documentation of IOException usage patterns

## Technical Details

**Location:** `src/Reader/StreamReader.php:83-105`

**Current Issue:**
```php
if (!file_exists($filePath)) {
    throw new IOException("File not found: {$filePath}");
    // Missing: $exception->setFilePath($filePath);
}

if (!is_readable($filePath)) {
    throw new IOException("File is not readable: {$filePath}");
    // Missing: $exception->setFilePath($filePath);
}
```

**Proposed Solution:**
```php
if (!file_exists($filePath)) {
    $exception = new IOException("File not found");
    $exception->setFilePath($filePath);
    throw $exception;
}

if (!is_readable($filePath)) {
    $exception = new IOException("File is not readable");
    $exception->setFilePath($filePath);
    throw $exception;
}

// Or with a helper method:
if (!file_exists($filePath)) {
    throw $this->createFileException("File not found", $filePath);
}

private function createFileException(string $message, string $filePath): IOException
{
    $exception = new IOException($message);
    $exception->setFilePath($filePath);
    return $exception;
}
```

**Benefits:**
- Structured error information (path separate from message)
- Easier to catch and handle specific file errors
- Better logging and debugging
- Consistent with existing exception design

## Dependencies
- Assumes `IOException` has `setFilePath()` method (verify implementation)

## Estimated Complexity
**Low** - Simple find and replace with consistent pattern

## Implementation Notes
- First, verify `IOException` implementation and available methods
- Search for all places where `IOException` is instantiated and thrown
- Common throw sites:
  - `StreamReader::fromFile()` - file not found, not readable
  - File open errors
  - Stream read errors (may not have file path)
  - Permission errors

**Search Commands:**
```bash
grep -r "throw new IOException" src/
grep -r "IOException::" src/
```

**Exception Design Review:**
Check if `IOException` has:
```php
class IOException extends JsonStreamException
{
    private ?string $filePath = null;

    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
}
```

**Test Cases:**
```php
// Verify exception contains file path
try {
    StreamReader::fromFile('/nonexistent/file.json');
} catch (IOException $e) {
    assertEquals('/nonexistent/file.json', $e->getFilePath());
    assertStringContains('File not found', $e->getMessage());
}
```

## Acceptance Criteria
- [ ] All file-related IOException instances use setFilePath()
- [ ] File path is separated from error message
- [ ] Tests verify file path is set in all scenarios
- [ ] Tests verify file path can be retrieved from exception
- [ ] Consider helper method to reduce boilerplate
- [ ] Documentation shows proper IOException usage
- [ ] All existing tests pass
- [ ] Code follows PSR-12 standards
- [ ] PHPStan analysis passes
