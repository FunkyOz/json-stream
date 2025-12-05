---
title: Exception Classes
status: done
priority: High
description: Implement all exception classes for error handling throughout the JsonStream package.
---

## Objectives
- Create base JsonStreamException
- Implement ParseException with line/column tracking
- Implement PathException for JSONPath errors
- Implement IOException for file/stream errors
- Ensure proper exception hierarchy

## Deliverables
1. `src/Exception/JsonStreamException.php` - Base exception
   - `getContext(): string`
   - `setContext(string $context): void`

2. `src/Exception/ParseException.php` - JSON parsing errors
   - Extends JsonStreamException
   - `getLine(): int`
   - `getColumn(): int`
   - `setPosition(int $line, int $column): void`

3. `src/Exception/PathException.php` - JSONPath errors
   - Extends JsonStreamException
   - `getPath(): string`
   - `setPath(string $path): void`

4. `src/Exception/IOException.php` - I/O errors
   - Extends JsonStreamException
   - `getFilePath(): string|null`
   - `setFilePath(string $filePath): void`

## API Reference
See API_SIGNATURE.md lines 1313-1462

## Dependencies
- Task 01: Project Setup

## Estimated Complexity
**Low** - Straightforward exception classes

## Acceptance Criteria
- [x] All four exception classes implemented
- [x] Proper inheritance hierarchy
- [x] All methods from API spec implemented
- [x] Full type hints (PHP 8.1+)
- [x] PHPDoc documentation
- [x] Unit tests for exception classes
